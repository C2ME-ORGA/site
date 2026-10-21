<?php

namespace App\Services;

final readonly class FollowUpDeliveryResult
{
    public function __construct(
        public bool $databaseSent,
        public bool $mailSent,
        public bool $mailSkipped = false,
        public ?string $databaseError = null,
        public ?string $mailError = null,
    ) {}

    public function hasFailures(): bool
    {
        return ! $this->databaseSent || ! $this->mailSent;
    }
}
