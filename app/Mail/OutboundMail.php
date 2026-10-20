<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class OutboundMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $attachmentPaths  chemins relatifs sur le disque « local »
     * @param  list<string>  $references  identifiants des messages cités
     */
    public function __construct(
        public readonly string $mailSubject,
        public readonly string $body,
        public readonly array $attachmentPaths = [],
        public readonly ?string $inReplyTo = null,
        public readonly array $references = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            references: $this->references,
            text: $this->inReplyTo !== null ? ['In-Reply-To' => $this->inReplyTo] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->body,
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (string $path): Attachment => Attachment::fromStorageDisk('local', $path),
            $this->attachmentPaths,
        );
    }
}
