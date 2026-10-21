<?php

namespace App\Mail;

use App\Enums\FollowUpReminderStage;
use App\Models\CrmFollowUpReminder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class FollowUpDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, CrmFollowUpReminder>  $reminders
     */
    public function __construct(
        public readonly User $user,
        public readonly Collection $reminders,
        public readonly FollowUpReminderStage $stage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->stage->notificationTitle($this->reminders->count()).' — '.now()->format('d/m/Y'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.follow-up-digest',
            with: [
                'user' => $this->user,
                'reminders' => $this->reminders,
                'stage' => $this->stage,
                'followUpsUrl' => $this->followUpsUrl(),
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

    public function followUpsUrl(): string
    {
        return url('/admin/relances?stage='.$this->stage->value);
    }
}
