<?php

namespace App\Mail;

use App\Models\QuoteRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly QuoteRequest $quote,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nouvelle demande de devis — '.$this->quote->kind->getLabel(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-request',
            with: [
                'quote' => $this->quote,
            ],
        );
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function headers(): array
    {
        return [
            'X-Mailer' => 'C2ME',
        ];
    }
}
