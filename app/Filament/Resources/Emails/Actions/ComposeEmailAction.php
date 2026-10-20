<?php

namespace App\Filament\Resources\Emails\Actions;

use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Services\Mail\MailboxMailer;
use App\Support\Mail\EmailBody;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ComposeEmailAction
{
    public static function make(?CrmAccount $account = null, ?CrmContact $contact = null): Action
    {
        return self::build('compose', 'Nouveau message', $account, $contact, null);
    }

    public static function reply(EmailMessage $message): Action
    {
        return self::build('reply', 'Répondre', $message->account, $message->contact, $message)
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('info')
            ->visible(fn (): bool => ! $message->is_sent);
    }

    private static function build(
        string $name,
        string $heading,
        ?CrmAccount $account,
        ?CrmContact $contact,
        ?EmailMessage $replyTo,
    ): Action {
        $recipient = $replyTo?->from_email ?? ($account !== null ? $account->email : $contact?->email);

        return Action::make($name)
            ->label($heading)
            ->icon($replyTo === null ? 'heroicon-o-pencil-square' : 'heroicon-o-arrow-uturn-left')
            ->color($replyTo === null ? 'primary' : 'info')
            ->modalHeading($heading)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitActionLabel('Envoyer')
            ->schema(fn (): array => [
                Select::make('mailbox_id')
                    ->label('Expéditeur')
                    ->options(self::mailboxOptions())
                    ->default(fn (): ?int => self::defaultMailboxId($replyTo))
                    ->required()
                    ->native(false),

                Select::make('to_contacts')
                    ->label('Contacts destinataires')
                    ->helperText('Sélectionner un ou plusieurs contacts du CRM.')
                    ->options(self::contactOptions())
                    ->searchable()
                    ->multiple()
                    ->placeholder('Rechercher un contact…')
                    ->required(fn (Get $get): bool => blank(trim((string) $get('to')))),

                TextInput::make('to')
                    ->label('Adresses e-mail')
                    ->helperText('Ou saisir des adresses séparées par une virgule.')
                    ->default($recipient)
                    ->required(fn (Get $get): bool => blank($get('to_contacts'))),

                Select::make('cc_contacts')
                    ->label('Contacts en copie')
                    ->options(self::contactOptions())
                    ->searchable()
                    ->multiple()
                    ->placeholder('Rechercher un contact…'),

                TextInput::make('cc')
                    ->label('Copie (Cc)')
                    ->helperText('Adresses séparées par une virgule.'),

                TextInput::make('subject')
                    ->label('Objet')
                    ->default(fn (): ?string => self::replySubject($replyTo))
                    ->required()
                    ->maxLength(255),

                RichEditor::make('body')
                    ->label('Message')
                    ->default(fn (): ?string => $replyTo !== null ? EmailBody::replyQuote($replyTo) : null)
                    ->disableToolbarButtons(['attachFiles'])
                    ->required()
                    ->columnSpanFull(),

                FileUpload::make('attachments')
                    ->label('Pièces jointes')
                    ->helperText('10 Mo maximum par fichier.')
                    ->multiple()
                    ->maxFiles(10)
                    ->maxSize(10240)
                    ->disk('local')
                    ->directory('mail-attachments')
                    ->preserveFilenames()
                    ->columnSpanFull(),
            ])
            ->action(function (array $data) use ($account, $contact, $replyTo): void {
                $mailbox = EmailMailbox::query()->findOrFail((int) $data['mailbox_id']);

                $to = self::recipients($data['to'] ?? null, $data['to_contacts'] ?? []);
                $cc = self::recipients($data['cc'] ?? null, $data['cc_contacts'] ?? []);

                if ($to === []) {
                    Notification::make()
                        ->title('Aucun destinataire valide')
                        ->danger()
                        ->send();

                    return;
                }

                $html = view('emails.compose', [
                    'body' => (string) $data['body'],
                ])->render();

                app(MailboxMailer::class)->send(
                    $mailbox,
                    $to,
                    (string) $data['subject'],
                    $html,
                    $cc,
                    $replyTo?->message_id,
                    $account?->id ?? $replyTo?->crm_account_id,
                    $contact?->id ?? $replyTo?->crm_contact_id,
                    self::attachmentPaths($data['attachments'] ?? null),
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

    private static function defaultMailboxId(?EmailMessage $replyTo = null): ?int
    {
        if ($replyTo !== null) {
            $receivedBy = self::accessibleMailboxes()
                ->whereKey($replyTo->email_mailbox_id)
                ->value('id');

            if ($receivedBy !== null) {
                return (int) $receivedBy;
            }
        }

        $shared = self::accessibleMailboxes()->where('is_shared', true)->value('id');

        return $shared !== null ? (int) $shared : self::accessibleMailboxes()->value('id');
    }

    private static function replySubject(?EmailMessage $replyTo): ?string
    {
        if ($replyTo === null || blank($replyTo->subject)) {
            return null;
        }

        $subject = EmailBody::decodeHeader((string) $replyTo->subject);

        return str_starts_with(mb_strtolower($subject), 're:') ? $subject : 'Re: '.$subject;
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
     * @return array<int, string>
     */
    private static function contactOptions(): array
    {
        return CrmContact::query()
            ->with('account')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where('do_not_contact', false)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->mapWithKeys(function (CrmContact $contact): array {
                $label = $contact->fullName() !== '' ? $contact->fullName() : $contact->email;
                $label .= ' <'.$contact->email.'>';

                if ($contact->account !== null) {
                    $label .= ' — '.$contact->account->name;
                }

                return [$contact->id => $label];
            })
            ->all();
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

    /**
     * @param  array<int, int|string>|null  $contactIds
     * @return list<string>
     */
    private static function contactEmails(?array $contactIds): array
    {
        $contactIds = array_values(array_filter(array_map('intval', $contactIds ?? [])));

        if ($contactIds === []) {
            return [];
        }

        return CrmContact::query()
            ->whereKey($contactIds)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->map(fn (string $email): string => trim($email))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int|string>|null  $contactIds
     * @return list<string>
     */
    private static function recipients(mixed $value, ?array $contactIds): array
    {
        return array_values(array_unique(array_merge(
            self::emails((string) $value),
            self::contactEmails($contactIds),
        )));
    }

    /**
     * @return list<string>
     */
    private static function attachmentPaths(mixed $value): array
    {
        return array_values(array_filter(
            (array) $value,
            fn (mixed $path): bool => is_string($path) && $path !== '',
        ));
    }
}
