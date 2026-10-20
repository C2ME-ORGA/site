<?php

namespace App\Http\Controllers;

use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldModeController extends Controller
{
    public function snapshot(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $accounts = CrmAccount::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->ownedBy($user))
            ->with([
                'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('last_name'),
                'activities' => fn ($query) => $query->orderByDesc('due_at')->limit(50),
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'accounts' => $accounts->map(fn (CrmAccount $account): array => [
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
                'next_follow_up_at' => $account->next_follow_up_at?->toIso8601String(),
                'notes' => $account->notes,
                'contacts' => $account->contacts->map(fn (CrmContact $contact): array => [
                    'id' => $contact->id,
                    'name' => $contact->fullName(),
                    'job_title' => $contact->job_title,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'mobile' => $contact->mobile,
                    'is_primary' => $contact->is_primary,
                ])->values(),
                'activities' => $account->activities
                    ->map(fn (CrmActivity $activity): array => $this->activityPayload($activity))
                    ->values(),
            ])->values(),
        ]);
    }

    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 401);

        $data = $request->validate([
            'activities' => ['present', 'array', 'max:200'],
            'activities.*.id' => ['required', 'integer'],
            'activities.*.body' => ['nullable', 'string', 'max:5000'],
            'activities.*.completed_at' => ['nullable', 'date'],
        ]);

        $updated = [];

        foreach ($data['activities'] as $payload) {
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
            'activities' => $updated,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function activityPayload(CrmActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'subject' => $activity->subject,
            'type_label' => $activity->type->getLabel(),
            'body' => $activity->body,
            'due_at' => $activity->due_at?->toIso8601String(),
            'completed_at' => $activity->completed_at?->toIso8601String(),
            'is_overdue' => $activity->isOverdue(),
            'updated_at' => $activity->updated_at?->toIso8601String(),
        ];
    }
}
