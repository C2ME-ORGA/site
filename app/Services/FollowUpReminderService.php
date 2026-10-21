<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\FollowUpReminderStage;
use App\Enums\UserRole;
use App\Models\CrmAccount;
use App\Models\CrmFollowUpReminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FollowUpReminderService
{
    public function __construct(
        private readonly FollowUpNotifier $notifier,
    ) {}

    /**
     * Prépare puis envoie les rappels d'une étape.
     */
    public function run(
        FollowUpReminderStage $stage,
        bool $dryRun = false,
        ?int $onlyUserId = null,
        bool $force = false,
        bool $retry = false,
    ): FollowUpReminderRunResult {
        if ($retry) {
            return $this->retry($stage, $dryRun, $onlyUserId);
        }

        return $this->dispatchForDueAccounts($stage, $dryRun, $onlyUserId, $force);
    }

    private function dispatchForDueAccounts(
        FollowUpReminderStage $stage,
        bool $dryRun,
        ?int $onlyUserId,
        bool $force,
    ): FollowUpReminderRunResult {
        $admins = $this->activeAdmins();
        $onlyUser = $onlyUserId !== null ? User::query()->find($onlyUserId) : null;

        $selectedAccounts = 0;
        $created = 0;
        $alreadySent = 0;
        $recipientIds = [];
        $reminderIds = [];
        $preview = [];

        $this->dueAccountsQuery($stage, $onlyUser)
            ->chunkById($this->batchSize(), function (Collection $accounts) use (
                $stage,
                $dryRun,
                $force,
                $admins,
                $onlyUser,
                &$selectedAccounts,
                &$created,
                &$alreadySent,
                &$recipientIds,
                &$reminderIds,
                &$preview,
            ): void {
                foreach ($accounts as $account) {
                    /** @var CrmAccount $account */
                    $selectedAccounts++;
                    $dueAt = $account->next_follow_up_at;

                    if ($dueAt === null) {
                        continue;
                    }

                    $recipients = $this->recipientsFor($account, $admins, $onlyUser?->id);

                    foreach ($recipients as $recipient) {
                        $recipientIds[$recipient->id] = true;

                        if ($dryRun) {
                            $preview[] = [
                                'account' => $account->name,
                                'recipient' => $recipient->name,
                                'due_at' => $dueAt->format('d/m/Y H:i'),
                                'stage' => $stage->getLabel(),
                            ];

                            continue;
                        }

                        $reminder = CrmFollowUpReminder::query()->firstOrCreate([
                            'crm_account_id' => $account->id,
                            'user_id' => $recipient->id,
                            'due_at' => $dueAt,
                            'stage' => $stage->value,
                        ]);

                        if ($reminder->wasRecentlyCreated) {
                            $created++;
                        }

                        if ($force || ! $reminder->isComplete()) {
                            $reminderIds[$reminder->id] = true;
                        } else {
                            $alreadySent++;
                        }
                    }
                }
            });

        if ($dryRun) {
            return new FollowUpReminderRunResult(
                accounts: $selectedAccounts,
                recipients: count($recipientIds),
                created: 0,
                alreadySent: 0,
                processed: 0,
                databaseSent: 0,
                databaseFailed: 0,
                mailSent: 0,
                mailFailed: 0,
                stale: 0,
                preview: $preview,
            );
        }

        $reminders = $this->loadReminders(array_keys($reminderIds));

        return $this->deliver(
            reminders: $reminders,
            stage: $stage,
            created: $created,
            alreadySent: $alreadySent,
            accounts: $selectedAccounts,
            recipients: count($recipientIds),
            force: $force,
        );
    }

    private function retry(
        FollowUpReminderStage $stage,
        bool $dryRun,
        ?int $onlyUserId,
    ): FollowUpReminderRunResult {
        $reminders = CrmFollowUpReminder::query()
            ->forStage($stage)
            ->where('due_at', '>=', now()->subDays($this->retryDays())->startOfDay())
            ->where(function (Builder $query): void {
                $query->whereNull('database_sent_at')
                    ->orWhereNull('mail_sent_at');
            })
            ->whereHas('account')
            ->when(
                $onlyUserId !== null,
                fn (Builder $query): Builder => $query->where('user_id', $onlyUserId),
            )
            ->with(['user', 'account.owner', 'account.contacts'])
            ->orderBy('due_at')
            ->get();

        if ($dryRun) {
            return $this->previewResult($reminders, $stage);
        }

        return $this->deliver(
            reminders: $reminders,
            stage: $stage,
            created: 0,
            alreadySent: 0,
        );
    }

    /**
     * @param  Collection<int, CrmFollowUpReminder>  $reminders
     */
    private function deliver(
        Collection $reminders,
        FollowUpReminderStage $stage,
        int $created,
        int $alreadySent,
        ?int $accounts = null,
        ?int $recipients = null,
        bool $force = false,
    ): FollowUpReminderRunResult {
        $total = $reminders->count();

        $valid = $reminders
            ->filter(function (CrmFollowUpReminder $reminder): bool {
                $account = $reminder->account;

                if ($account === null) {
                    $this->deleteIfNeverSent($reminder);

                    return false;
                }

                if ($account->next_follow_up_at === null || ! $account->next_follow_up_at->equalTo($reminder->due_at)) {
                    $this->deleteIfNeverSent($reminder);

                    return false;
                }

                return true;
            })
            ->values();

        $databaseSent = 0;
        $databaseFailed = 0;
        $mailSent = 0;
        $mailFailed = 0;

        foreach ($valid->groupBy('user_id') as $userReminders) {
            $userReminders = $userReminders->values();
            $user = $userReminders->first()?->user;

            if ($user === null || ! $user->is_active) {
                continue;
            }

            $databaseReminders = $force
                ? $userReminders
                : $userReminders->filter(fn (CrmFollowUpReminder $reminder): bool => $reminder->needsDatabase())->values();

            $mailReminders = $force
                ? $userReminders
                : $userReminders->filter(fn (CrmFollowUpReminder $reminder): bool => $reminder->needsMail())->values();

            if ($databaseReminders->isEmpty() && $mailReminders->isEmpty()) {
                continue;
            }

            $result = $this->notifier->send($user, $databaseReminders, $mailReminders, $stage);

            if ($databaseReminders->isNotEmpty()) {
                if ($result->databaseSent) {
                    $databaseSent++;
                } else {
                    $databaseFailed++;
                }
            }

            if ($mailReminders->isNotEmpty() && ! $result->mailSkipped) {
                if ($result->mailSent) {
                    $mailSent++;
                } else {
                    $mailFailed++;
                }
            }

            $this->markDelivery($userReminders, $databaseReminders, $mailReminders, $result);
        }

        return new FollowUpReminderRunResult(
            accounts: $accounts ?? $valid->pluck('crm_account_id')->unique()->count(),
            recipients: $recipients ?? $valid->pluck('user_id')->unique()->count(),
            created: $created,
            alreadySent: $alreadySent,
            processed: $valid->count(),
            databaseSent: $databaseSent,
            databaseFailed: $databaseFailed,
            mailSent: $mailSent,
            mailFailed: $mailFailed,
            stale: $total - $valid->count(),
        );
    }

    /**
     * @param  Collection<int, CrmFollowUpReminder>  $reminders
     */
    private function previewResult(Collection $reminders, FollowUpReminderStage $stage): FollowUpReminderRunResult
    {
        $preview = $reminders
            ->map(fn (CrmFollowUpReminder $reminder): array => [
                'account' => $reminder->account->name,
                'recipient' => $reminder->user->name,
                'due_at' => $reminder->due_at->format('d/m/Y H:i'),
                'stage' => $stage->getLabel(),
            ])
            ->values()
            ->all();

        return new FollowUpReminderRunResult(
            accounts: $reminders->pluck('crm_account_id')->unique()->count(),
            recipients: $reminders->pluck('user_id')->unique()->count(),
            created: 0,
            alreadySent: 0,
            processed: 0,
            databaseSent: 0,
            databaseFailed: 0,
            mailSent: 0,
            mailFailed: 0,
            stale: 0,
            preview: $preview,
        );
    }

    /**
     * @param  list<int>  $reminderIds
     * @return Collection<int, CrmFollowUpReminder>
     */
    private function loadReminders(array $reminderIds): Collection
    {
        if ($reminderIds === []) {
            return collect();
        }

        return CrmFollowUpReminder::query()
            ->whereIn('id', $reminderIds)
            ->with(['user', 'account.owner', 'account.contacts'])
            ->orderBy('due_at')
            ->get();
    }

    /**
     * @param  Collection<int, CrmFollowUpReminder>  $reminders
     * @param  Collection<int, CrmFollowUpReminder>  $databaseReminders
     * @param  Collection<int, CrmFollowUpReminder>  $mailReminders
     */
    private function markDelivery(
        Collection $reminders,
        Collection $databaseReminders,
        Collection $mailReminders,
        FollowUpDeliveryResult $result,
    ): void {
        $sentAt = now();
        $databaseIds = array_flip($databaseReminders->pluck('id')->all());
        $mailIds = array_flip($mailReminders->pluck('id')->all());

        $failedIds = [];

        if (! $result->databaseSent) {
            foreach ($databaseReminders as $reminder) {
                $failedIds[$reminder->id] = true;
            }
        }

        if (! $result->mailSent) {
            foreach ($mailReminders as $reminder) {
                $failedIds[$reminder->id] = true;
            }
        }

        $errors = array_values(array_filter(
            [$result->databaseError, $result->mailError],
            fn (?string $error): bool => $error !== null,
        ));

        foreach ($reminders as $reminder) {
            $attributes = [];

            if ($result->databaseSent && isset($databaseIds[$reminder->id])) {
                $attributes['database_sent_at'] = $sentAt;
            }

            if ($result->mailSent && isset($mailIds[$reminder->id])) {
                $attributes['mail_sent_at'] = $sentAt;
            }

            if (isset($failedIds[$reminder->id])) {
                $attributes['attempts'] = $reminder->attempts + 1;
                $attributes['last_error'] = $errors === [] ? 'Erreur inconnue' : implode(' | ', $errors);
            }

            if ($attributes !== []) {
                $reminder->forceFill($attributes)->save();
            }
        }
    }

    private function deleteIfNeverSent(CrmFollowUpReminder $reminder): void
    {
        if ($reminder->needsDatabase() && $reminder->needsMail()) {
            $reminder->delete();
        }
    }

    /**
     * @return Builder<CrmAccount>
     */
    private function dueAccountsQuery(FollowUpReminderStage $stage, ?User $onlyUser): Builder
    {
        $query = CrmAccount::query()
            ->whereNotNull('next_follow_up_at')
            ->with(['owner', 'contacts']);

        if ((bool) config('crm.follow_ups.exclude_inactive', true)) {
            $query->where('status', '!=', AccountStatus::Inactive->value);
        }

        if ($stage === FollowUpReminderStage::DayBefore) {
            $query->whereDate('next_follow_up_at', now()->addDay()->toDateString());
        } else {
            $query->where('next_follow_up_at', '<=', now()->endOfDay());
        }

        if ($onlyUser !== null && ! $onlyUser->isAdmin()) {
            $query->where('owner_id', $onlyUser->id);
        }

        return $query;
    }

    /**
     * @param  Collection<int, User>  $admins
     * @return Collection<int, User>
     */
    private function recipientsFor(CrmAccount $account, Collection $admins, ?int $onlyUserId): Collection
    {
        $recipients = $admins->values();
        $owner = $account->owner;

        if ($owner !== null && $owner->is_active) {
            $recipients->push($owner);
        }

        $recipients = $recipients->unique('id')->values();

        if ($onlyUserId !== null) {
            $recipients = $recipients
                ->filter(fn (User $user): bool => $user->id === $onlyUserId)
                ->values();
        }

        return $recipients;
    }

    /**
     * @return Collection<int, User>
     */
    private function activeAdmins(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where('role', UserRole::Admin->value)
            ->orderBy('name')
            ->get();
    }

    private function batchSize(): int
    {
        return max(1, (int) config('crm.follow_ups.batch_size', 100));
    }

    private function retryDays(): int
    {
        return max(1, (int) config('crm.follow_ups.retry_days', 7));
    }
}
