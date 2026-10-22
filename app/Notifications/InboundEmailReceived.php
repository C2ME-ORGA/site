<?php

namespace App\Notifications;

use App\Filament\Resources\Emails\EmailMessageResource;
use App\Models\EmailMessage;
use Filament\Actions\Action as FilamentAction;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class InboundEmailReceived extends Notification
{
    public function __construct(
        public readonly EmailMessage $message,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->filamentNotification()->getDatabaseMessage();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $data = $this->filamentNotification()->toArray();
        $data['format'] = 'filament';

        return new BroadcastMessage($data);
    }

    private function filamentNotification(): FilamentNotification
    {
        $sender = $this->decodeMime($this->message->from_name) ?? $this->message->from_email;
        $subject = filled($this->message->subject)
            ? Str::limit($this->decodeMime($this->message->subject) ?? '', 90)
            : '(sans objet)';

        return FilamentNotification::make()
            ->title('Nouvel e-mail de '.$sender)
            ->body($subject)
            ->icon('heroicon-o-envelope')
            ->color('info')
            ->actions([
                FilamentAction::make('view')
                    ->label('Ouvrir le message')
                    ->url(EmailMessageResource::getUrl('view', ['record' => $this->message]))
                    ->color('primary'),
            ]);
    }

    private function decodeMime(?string $value): ?string
    {
        if ($value === null || $value === '' || ! function_exists('iconv_mime_decode')) {
            return $value;
        }

        $decoded = iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded === false ? $value : $decoded;
    }
}
