<?php

namespace App\Http\Controllers;

use App\Enums\CrmActivityType;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FieldModeController extends Controller
{
    public function snapshot(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $accounts = CrmAccount::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->ownedBy($user))
            ->with([
                'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('last_name'),
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'accounts' => $accounts->map(fn (CrmAccount $account): array => [
                ...$this->accountPayload($account),
                'contacts' => $account->contacts
                    ->map(fn (CrmContact $contact): array => $this->contactPayload($contact))
                    ->values(),
            ])->values(),
        ]);
    }

    public function activities(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $data = $request->validate([
            'after_id' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $limit = (int) ($data['limit'] ?? 200);
        $afterId = (int) ($data['after_id'] ?? 0);

        $activities = CrmActivity::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->whereHas(
                'account',
                fn ($query) => $query->where('owner_id', $user->id),
            ))
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
            ->when(! $user->isAdmin(), fn ($query) => $query->forUser($user))
            ->whereKey((int) ($operation['entity_id'] ?? 0))
            ->first();

        if ($activity === null) {
            return $this->operationError($opId, ['activity' => ['Activité inaccessible.']]);
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

        $contact->fill($validator->validated())->save();

        return $this->operationOk($opId, 'contact', $contact);
    }

    /**
     * @return array<string, mixed>
     */
    private function operationOk(string $opId, string $entityType, CrmAccount|CrmActivity|CrmContact $entity, ?string $clientUuid = null): array
    {
        $server = $entity instanceof CrmActivity
            ? $this->activityPayload($entity)
            : ($entity instanceof CrmAccount
                ? $this->accountPayload($entity)
                : $this->contactPayload($entity));

        return [
            'op_id' => $opId,
            'status' => 'ok',
            'entity_type' => $entityType,
            'entity_id' => $entity->getKey(),
            'client_uuid' => $clientUuid,
            'updated_at' => $entity->updated_at?->toIso8601String(),
            'server' => $server,
        ];
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
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }
}
