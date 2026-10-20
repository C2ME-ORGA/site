<?php

namespace App\Support\Mail;

/**
 * Pièce jointe d'un message entrant, déjà décodée en mémoire.
 */
class InboundAttachment
{
    public function __construct(
        public readonly string $filename,
        public readonly ?string $mimeType,
        public readonly int $size,
        public readonly string $contents,
    ) {}
}
