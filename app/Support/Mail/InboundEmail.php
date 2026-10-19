<?php

namespace App\Support\Mail;

use Carbon\CarbonImmutable;

/**
 * Représentation neutre d'un message entrant, indépendante du client IMAP.
 */
class InboundEmail
{
    /**
     * @param  list<array{email: string, name: string|null}>  $to
     * @param  list<array{email: string, name: string|null}>  $cc
     */
    public function __construct(
        public readonly string $uid,
        public readonly ?string $messageId,
        public readonly ?string $inReplyTo,
        public readonly ?string $references,
        public readonly string $fromEmail,
        public readonly ?string $fromName,
        public readonly array $to,
        public readonly array $cc,
        public readonly ?string $subject,
        public readonly ?string $textBody,
        public readonly ?string $htmlBody,
        public readonly ?CarbonImmutable $date,
        public readonly bool $isRead,
    ) {}
}
