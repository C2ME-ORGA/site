<?php

namespace App\Filament\Resources\Emails\Actions;

use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Services\Mail\MailboxMailer;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class ComposeEmailAction
{
    public static function make(?CrmAccount $account = null, ?CrmContact $contact = null): Action
    {
        return self::build($account, $contact);
    }

    public static function reply(EmailMessage $message): Action
    {
        return self::build(
            $message->account,
            $message->contact,
            [
                'mailbox_id' => $message->email_mailbox_id,
                'to' => $message->from_email,
                'subject' => self::replySubject((string) $message->subject),
                'body' => self::quotedBody($message),
            ],
            $message,
        );
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    private static function build(
        ?CrmAccount $account,
        ?CrmContact $contact,
        array $defaults = [],
        ?EmailMessage $replyTo = null,
    ): Action {
        $mailboxOptions = self::mailboxOptions();
        $defaultMailbox = $defaults['mailbox_id'] ?? self::defaultMailboxId();

        if ($defaultMailbox === null || ! array_key_exists((int) $defaultMailbox, $mailboxOptions)) {
            $defaultMailbox = self::defaultMailboxId();
        }

        $recipient = $defaults['to'] ?? ($account !== null ? $account->email : $contact?->email);
        $isReply = $replyTo !== null;

        return Action::make($isReply ? 'reply' : 'compose')
            ->label($isReply ? 'Répondre' : 'Nouveau message')
            ->icon($isReply ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-pencil-square')
            ->color($isReply ? 'info' : 'primary')
            ->modalHeading($isReply ? 'Répondre au message' : 'Nouveau message')
            ->modalSubmitActionLabel('Envoyer')
            ->schema(fn (): array => [
                Select::make('mailbox_id')
                    ->label('Expéditeur')
                    ->options($mailboxOptions)
                    ->default($defaultMailbox)
                    ->required()
                    ->native(false),

                TextInput::make('to')
                    ->label('Destinataire(s)')
                    ->helperText('Séparer plusieurs adresses par une virgule.')
                    ->default($recipient)
                    ->required(),

                TextInput::make('cc')
                    ->label('Copie (Cc)'),

                TextInput::make('subject')
                    ->label('Objet')
                    ->default($defaults['subject'] ?? null)
                    ->required()
                    ->maxLength(255),

                Textarea::make('body')
                    ->label('Message')
                    ->default($defaults['body'] ?? null)
                    ->rows(8)
                    ->required(),

                FileUpload::make('attachments')
                    ->label('Pièces jointes')
                    ->multiple()
                    ->disk('local')
                    ->directory('mail-attachments')
                    ->preserveFilenames()
                    ->maxSize(10240),
            ])
            ->action(function (array $data) use ($account, $contact, $replyTo): void {
                $mailbox = EmailMailbox::query()->findOrFail((int) $data['mailbox_id']);

                app(MailboxMailer::class)->send(
                    $mailbox,
                    self::emails((string) $data['to']),
                    (string) $data['subject'],
                    nl2br(htmlspecialchars((string) $data['body'], ENT_QUOTES)),
                    self::emails((string) ($data['cc'] ?? '')),
                    $replyTo?->message_id,
                    $account?->id,
                    $contact?->id,
                    array_values((array) ($data['attachments'] ?? [])),
                    $replyTo !== null ? self::references($replyTo) : [],
                );

                Notification::make()
                    ->title('Message envoyé')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, string>
     */
    private static function mailboxOptions(): array
    {
        return self::accessibleMailboxes()
            ->get()
            ->mapWithKeys(fn (EmailMailbox $mailbox): array => [
                $mailbox->id => $mailbox->name.' <'.$mailbox->email.'>',
            ])
            ->all();
    }

    private static function defaultMailboxId(): ?int
    {
        $shared = self::accessibleMailboxes()->where('is_shared', true)->value('id');

        return $shared !== null ? (int) $shared : self::accessibleMailboxes()->value('id');
    }

    /**
     * @return Builder<EmailMailbox>
     */
    private static function accessibleMailboxes(): Builder
    {
        $query = EmailMailbox::query()->where('is_active', true);
        $user = auth()->user();

        if ($user !== null && ! $user->isAdmin()) {
            $query->where(fn (Builder $query) => $query
                ->where('is_shared', true)
                ->orWhere('user_id', $user->id));
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private static function emails(string $value): array
    {
        return array_values(array_filter(array_map(
            fn (string $email): string => trim($email),
            explode(',', $value),
        )));
    }

    private static function replySubject(string $subject): string
    {
        return str_starts_with(mb_strtolower($subject), 're:')
            ? $subject
            : 'Re: '.$subject;
    }

    private static function quotedBody(EmailMessage $message): string
    {
        $text = $message->body_text !== null && $message->body_text !== ''
            ? $message->body_text
            : strip_tags((string) $message->body_html);

        $header = sprintf(
            'Le %s, %s a écrit :',
            $message->received_at?->format('d/m/Y H:i') ?? '',
            $message->from_name ?? $message->from_email,
        );

        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        $quoted = implode("\n", array_map(fn (string $line): string => '> '.$line, $lines));

        return "\n\n".$header."\n".$quoted;
    }

    /**
     * @return list<string>
     */
    private static function references(EmailMessage $message): array
    {
        $references = $message->references !== null && $message->references !== ''
            ? (preg_split('/\s+/', trim($message->references)) ?: [])
            : [];

        if ($message->message_id !== null && $message->message_id !== '') {
            $references[] = $message->message_id;
        }

        return array_values(array_unique(array_filter($references)));
    }
}
