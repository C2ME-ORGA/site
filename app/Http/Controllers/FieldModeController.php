<?php

namespace App\Http\Controllers;

use App\Enums\CrmActivityType;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmActivityAttachment;
use App\Models\CrmContact;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FieldModeController extends Controller
{
    public function snapshot(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $data = $request->validate([
            'updated_since' => ['nullable', 'date'],
        ]);

        $since = isset($data['updated_since']) ? CarbonImmutable::parse($data['updated_since']) : null;
        $generatedAt = now();

        $accounts = CrmAccount::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->ownedBy($user))
            ->when($since !== null, fn ($query) => $query->where(
                fn ($query) => $query
                    ->where('updated_at', '>=', $since)
                    ->orWhereHas('contacts', fn ($query) => $query->where('updated_at', '>=', $since))
                    ->orWhereHas('contacts', fn ($query) => $query->onlyTrashed()->where('deleted_at', '>=', $since)),
            ))
            ->with([
                'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('last_name'),
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'generated_at' => $generatedAt->toIso8601String(),
            'cursor' => $generatedAt->toIso8601String(),
            'full' => $since === null,
            'accounts' => $accounts->map(fn (CrmAccount $account): array => [
                ...$this->accountPayload($account),
                'contacts' => $account->contacts
                    ->map(fn (CrmContact $contact): array => $this->contactPayload($contact))
                    ->values(),
            ])->values(),
            'deleted' => [
                'accounts' => $since === null ? [] : $this->deletedAccountIds($user, $since),
                'contacts' => $since === null ? [] : $this->deletedContactIds($user, $since),
            ],
        ]);
    }

    /**
     * @return list<int>
     */
    private function deletedAccountIds(User $user, CarbonImmutable $since): array
    {
        $ids = CrmAccount::query()
            ->onlyTrashed()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('owner_id', $user->id))
            ->where('deleted_at', '>=', $since)
            ->pluck('id')
            ->all();

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $ids));
    }

    /**
     * @return list<int>
     */
    private function deletedContactIds(User $user, CarbonImmutable $since): array
    {
        $ids = CrmContact::query()
            ->onlyTrashed()
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas(
                'account',
                fn ($query) => $query->withTrashed()->where('owner_id', $user->id),
            ))
            ->where('deleted_at', '>=', $since)
            ->pluck('id')
            ->all();

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $ids));
    }

    public function activities(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'updated_since' => ['nullable', 'date'],
        ]);

        $limit = (int) ($data['limit'] ?? 200);
        $afterId = (int) ($data['after_id'] ?? 0);
        $since = isset($data['updated_since']) ? CarbonImmutable::parse($data['updated_since']) : null;

        $activities = CrmActivity::query()
            ->with('attachments')
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas(
                'account',
                fn ($query) => $query->where('owner_id', $user->id),
            ))
            ->when($since !== null, fn ($query) => $query->where('updated_at', '>=', $since))
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $activities->count() > $limit;
        $page = $activities->take($limit)->values();

        return response()->json([
            'activities' => $page
                ->map(fn (CrmActivity $activity): array => $this->activityPayload($activity))
                ->values(),
            'next_cursor' => $hasMore ? $page->last()?->id : null,
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $data = $request->validate([
            'operations' => ['sometimes', 'array', 'max:200'],
            'operations.*.op_id' => ['required', 'string', 'max:64'],
            'operations.*.type' => ['required', Rule::in([
                'create_activity',
                'update_activity',
                'update_account',
                'update_contact',
            ])],
            'operations.*.entity_id' => ['nullable', 'integer'],
            'operations.*.client_uuid' => ['nullable', 'string', 'max:64'],
            'operations.*.payload' => ['required', 'array'],
            'operations.*.base_updated_at' => ['nullable', 'date'],
            'activities' => ['sometimes', 'array', 'max:200'],
            'activities.*.id' => ['required', 'integer'],
            'activities.*.body' => ['nullable', 'string', 'max:5000'],
            'activities.*.completed_at' => ['nullable', 'date'],
        ]);

        $results = [];

        foreach ($data['operations'] ?? [] as $operation) {
            $results[] = $this->applyOperation($user, $operation);
        }

        $updated = [];

        foreach ($data['activities'] ?? [] as $payload) {
            $activity = CrmActivity::query()
                ->with('attachments')
                ->when(! $user->isAdmin(), fn ($query) => $query->forUser($user))
                ->whereKey($payload['id'])
                ->first();

            if ($activity === null) {
                continue;
            }

            $activity->body = $payload['body'] ?? null;
            $activity->completed_at = isset($payload['completed_at'])
                ? CarbonImmutable::parse($payload['completed_at'])
                : null;

            $activity->save();

            $updated[] = $this->activityPayload($activity);
        }

        return response()->json([
            'synced_at' => now()->toIso8601String(),
            'results' => $results,
            'activities' => $updated,
        ]);
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function applyOperation(User $user, array $operation): array
    {
        $opId = (string) $operation['op_id'];
        $type = (string) $operation['type'];
        $payload = (array) $operation['payload'];

        return match ($type) {
            'create_activity' => $this->createActivity($user, $opId, $operation, $payload),
            'update_activity' => $this->updateActivity($user, $opId, $operation, $payload),
            'update_account' => $this->updateAccount($user, $opId, $operation, $payload),
            'update_contact' => $this->updateContact($user, $opId, $operation, $payload),
            default => $this->operationError($opId, ['type' => ['Type d’opération inconnu.']]),
        };
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createActivity(User $user, string $opId, array $operation, array $payload): array
    {
        $validator = Validator::make($payload, [
            'crm_account_id' => ['required', 'integer'],
            'crm_contact_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::in(array_column(CrmActivityType::cases(), 'value'))],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->operationError($opId, $validator->errors()->toArray());
        }

        $data = $validator->validated();

        $account = CrmAccount::query()->whereKey($data['crm_account_id'])->first();

        if ($account === null || Gate::denies('update', $account)) {
            return $this->operationError($opId, ['crm_account_id' => ['Compte inaccessible.']]);
        }

        if (isset($data['crm_contact_id'])) {
            $contactExists = CrmContact::query()
                ->whereKey($data['crm_contact_id'])
                ->where('crm_account_id', $account->id)
                ->exists();

            if (! $contactExists) {
                return $this->operationError($opId, ['crm_contact_id' => ['Contact inaccessible pour ce compte.']]);
            }
        }

        $clientUuid = $operation['client_uuid'] ?? null;

        if (is_string($clientUuid) && $clientUuid !== '') {
            $existing = CrmActivity::query()->where('client_uuid', $clientUuid)->first();

            if ($existing !== null) {
                if (Gate::denies('update', $existing)) {
                    return $this->operationError($opId, ['client_uuid' => ['Identifiant d’activité déjà utilisé.']]);
                }

                return $this->operationOk($opId, 'activity', $existing, $clientUuid);
            }
        }

        $activity = CrmActivity::create([
            'client_uuid' => is_string($clientUuid) && $clientUuid !== '' ? $clientUuid : null,
            'crm_account_id' => $account->id,
            'crm_contact_id' => $data['crm_contact_id'] ?? null,
            'user_id' => $user->id,
            'type' => CrmActivityType::from((string) $data['type']),
            'subject' => (string) $data['subject'],
            'body' => $data['body'] ?? null,
            'due_at' => isset($data['due_at']) ? CarbonImmutable::parse($data['due_at']) : null,
            'completed_at' => isset($data['completed_at']) ? CarbonImmutable::parse($data['completed_at']) : null,
        ]);

        $activity->setRelation('attachments', collect());

        return $this->operationOk($opId, 'activity', $activity, $clientUuid);
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateActivity(User $user, string $opId, array $operation, array $payload): array
    {
        $validator = Validator::make($payload, [
            'body' => ['nullable', 'string', 'max:5000'],
            'completed_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            return $this->operationError($opId, $validator->errors()->toArray());
        }

        $activity = CrmActivity::query()
            ->with('attachments')
            ->when(! $user->isAdmin(), fn ($query) => $query->forUser($user))
            ->whereKey((int) ($operation['entity_id'] ?? 0))
            ->first();

        if ($activity === null) {
            return $this->operationError($opId, ['activity' => ['Activité inaccessible.']]);
        }

        $conflict = $this->conflictResult($opId, 'activity', $activity, $operation['base_updated_at'] ?? null);

        if ($conflict !== null) {
            return $conflict;
        }

        $activity->fill($validator->validated())->save();

        return $this->operationOk($opId, 'activity', $activity);
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateAccount(User $user, string $opId, array $operation, array $payload): array
    {
        $validator = Validator::make($payload, [
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:32'],
            'address_line1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line2' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:16'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return $this->operationError($opId, $validator->errors()->toArray());
        }

        $account = CrmAccount::query()->whereKey((int) ($operation['entity_id'] ?? 0))->first();

        if ($account === null || Gate::denies('update', $account)) {
            return $this->operationError($opId, ['account' => ['Compte inaccessible.']]);
        }

        $conflict = $this->conflictResult($opId, 'account', $account, $operation['base_updated_at'] ?? null);

        if ($conflict !== null) {
            return $conflict;
        }

        $account->fill($validator->validated())->save();

        return $this->operationOk($opId, 'account', $account);
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function updateContact(User $user, string $opId, array $operation, array $payload): array
    {
        $validator = Validator::make($payload, [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:32'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        if ($validator->fails()) {
            return $this->operationError($opId, $validator->errors()->toArray());
        }

        $contact = CrmContact::query()->whereKey((int) ($operation['entity_id'] ?? 0))->first();

        if ($contact === null || Gate::denies('update', $contact)) {
            return $this->operationError($opId, ['contact' => ['Contact inaccessible.']]);
        }

        $conflict = $this->conflictResult($opId, 'contact', $contact, $operation['base_updated_at'] ?? null);

        if ($conflict !== null) {
            return $conflict;
        }

        $contact->fill($validator->validated())->save();

        return $this->operationOk($opId, 'contact', $contact);
    }

    /**
     * @return array<string, mixed>
     */
    private function operationOk(string $opId, string $entityType, CrmAccount|CrmActivity|CrmContact $entity, ?string $clientUuid = null): array
    {
        return [
            'op_id' => $opId,
            'status' => 'ok',
            'entity_type' => $entityType,
            'entity_id' => $entity->getKey(),
            'client_uuid' => $clientUuid,
            'updated_at' => $entity->updated_at?->toIso8601String(),
            'server' => $this->entityPayload($entity),
        ];
    }

    /**
     * Détecte une modification concurrente en comparant la version connue du
     * client (`base_updated_at`) à la version serveur actuelle.
     *
     * @return array<string, mixed>|null
     */
    private function conflictResult(string $opId, string $entityType, CrmAccount|CrmActivity|CrmContact $entity, mixed $baseUpdatedAt): ?array
    {
        if (! is_string($baseUpdatedAt) || $baseUpdatedAt === '' || $entity->updated_at === null) {
            return null;
        }

        try {
            $base = CarbonImmutable::parse($baseUpdatedAt);
        } catch (Throwable) {
            return null;
        }

        if ($base->equalTo($entity->updated_at)) {
            return null;
        }

        return [
            'op_id' => $opId,
            'status' => 'conflict',
            'entity_type' => $entityType,
            'entity_id' => $entity->getKey(),
            'updated_at' => $entity->updated_at->toIso8601String(),
            'server' => $this->entityPayload($entity),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entityPayload(CrmAccount|CrmActivity|CrmContact $entity): array
    {
        return match (true) {
            $entity instanceof CrmActivity => $this->activityPayload($entity),
            $entity instanceof CrmAccount => $this->accountPayload($entity),
            default => $this->contactPayload($entity),
        };
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     * @return array<string, mixed>
     */
    private function operationError(string $opId, array $errors): array
    {
        return [
            'op_id' => $opId,
            'status' => 'error',
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accountPayload(CrmAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'book' => $account->book->value,
            'book_label' => $account->book->getLabel(),
            'category_label' => $account->category->getLabel(),
            'status' => $account->status->value,
            'status_label' => $account->status->getLabel(),
            'email' => $account->email,
            'phone' => $account->phone,
            'mobile' => $account->mobile,
            'address' => $account->fullAddress(),
            'address_line1' => $account->address_line1,
            'address_line2' => $account->address_line2,
            'postal_code' => $account->postal_code,
            'city' => $account->city,
            'country' => $account->country,
            'next_follow_up_at' => $account->next_follow_up_at?->toIso8601String(),
            'notes' => $account->notes,
            'updated_at' => $account->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contactPayload(CrmContact $contact): array
    {
        return [
            'id' => $contact->id,
            'crm_account_id' => $contact->crm_account_id,
            'name' => $contact->fullName(),
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'job_title' => $contact->job_title,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'mobile' => $contact->mobile,
            'notes' => $contact->notes,
            'is_primary' => $contact->is_primary,
            'updated_at' => $contact->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activityPayload(CrmActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'client_uuid' => $activity->client_uuid,
            'crm_account_id' => $activity->crm_account_id,
            'crm_contact_id' => $activity->crm_contact_id,
            'subject' => $activity->subject,
            'type' => $activity->type->value,
            'type_label' => $activity->type->getLabel(),
            'body' => $activity->body,
            'due_at' => $activity->due_at?->toIso8601String(),
            'completed_at' => $activity->completed_at?->toIso8601String(),
            'is_overdue' => $activity->isOverdue(),
            'updated_at' => $activity->updated_at?->toIso8601String(),
            'attachments' => $activity->attachments
                ->map(fn (CrmActivityAttachment $attachment): array => $attachment->payload())
                ->values(),
        ];
    }

    /**
     * Ajoute une photo à une activité. L'identifiant `client_uuid` rend
     * l'upload idempotent lorsqu'une requête est rejouée hors ligne.
     */
    public function storeAttachment(Request $request, CrmActivity $activity): JsonResponse
    {
        $this->user($request);

        Gate::authorize('update', $activity);

        $maxSizeKb = (int) config('crm.attachments.max_size_kb', 5 * 1024);
        $maxCount = (int) config('crm.attachments.max_count', 5);
        $mimeTypes = (array) config('crm.attachments.mime_types', ['image/jpeg', 'image/png', 'image/webp']);

        $data = $request->validate([
            'photo' => ['required', 'file', 'mimetypes:'.implode(',', $mimeTypes), 'max:'.$maxSizeKb],
            'client_uuid' => ['nullable', 'uuid'],
        ]);

        $clientUuid = isset($data['client_uuid']) ? (string) $data['client_uuid'] : null;

        if ($clientUuid !== null) {
            $existing = CrmActivityAttachment::query()->where('client_uuid', $clientUuid)->first();

            if ($existing !== null) {
                abort_if($existing->crm_activity_id !== $activity->id, 409);

                return response()->json(['attachment' => $existing->payload()]);
            }
        }

        abort_if($activity->attachments()->count() >= $maxCount, 422, 'Nombre maximum de photos atteint.');

        $photo = $request->file('photo');

        abort_if($photo === null, 422, 'Photo manquante.');

        $extension = match ($photo->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $directory = trim((string) config('crm.attachments.directory', 'crm-activity-attachments'), '/').'/'.$activity->id;
        $path = Storage::disk(CrmActivityAttachment::disk())->putFileAs(
            $directory,
            $photo,
            Str::uuid()->toString().'.'.$extension,
        );

        abort_if($path === false, 500, 'Enregistrement de la photo impossible.');

        try {
            $attachment = $activity->attachments()->create([
                'client_uuid' => $clientUuid,
                'path' => $path,
                'original_name' => $photo->getClientOriginalName(),
                'mime_type' => $photo->getMimeType(),
                'size' => (int) $photo->getSize(),
            ]);
        } catch (Throwable $exception) {
            Storage::disk(CrmActivityAttachment::disk())->delete($path);

            throw $exception;
        }

        return response()->json(['attachment' => $attachment->payload()], 201);
    }

    /**
     * Sert une photo via une route authentifiée, jamais depuis le disque public.
     */
    public function showAttachment(Request $request, CrmActivityAttachment $attachment): StreamedResponse
    {
        $this->user($request);

        $activity = $attachment->activity;

        abort_if($activity === null, 404);

        Gate::authorize('view', $activity);

        abort_unless(Storage::disk(CrmActivityAttachment::disk())->exists($attachment->path), 404);

        return Storage::disk(CrmActivityAttachment::disk())->response(
            $attachment->path,
            $attachment->original_name,
            [
                'Content-Type' => $attachment->mime_type ?? 'application/octet-stream',
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
