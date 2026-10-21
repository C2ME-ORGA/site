<?php

namespace App\Services;

final readonly class FollowUpReminderRunResult
{
    /**
     * @param  array<int, array{account: string, recipient: string, due_at: string, stage: string}>  $preview
     */
    public function __construct(
        public int $accounts,
        public int $recipients,
        public int $created,
        public int $alreadySent,
        public int $processed,
        public int $databaseSent,
        public int $databaseFailed,
        public int $mailSent,
        public int $mailFailed,
        public int $stale,
        public array $preview = [],
    ) {}
}
