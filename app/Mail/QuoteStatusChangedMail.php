<?php

namespace App\Mail;

use App\Enums\QuoteDocumentStatus;
use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Quote $quote,
        public readonly QuoteDocumentStatus $fromStatus,
        public readonly QuoteDocumentStatus $toStatus,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Devis '.$this->quote->reference.' — '.$this->toStatus->getLabel(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-status',
            with: [
                'quote' => $this->quote,
                'fromStatus' => $this->fromStatus,
                'toStatus' => $this->toStatus,
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [
            'X-Mailer' => 'C2ME',
        ];
    }
}
