<?php

use App\Contracts\InboundFetcher;
use App\Enums\CrmBook;
use App\Enums\UserRole;
use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Models\User;
use App\Notifications\InboundEmailReceived;
use App\Services\MailboxSynchronizer;
use App\Support\Mail\InboundAttachment;
use App\Support\Mail\InboundEmail;
use Carbon\CarbonImmutable;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * @param  list<InboundEmail>  $messages
 */
function bindFetcher(array $messages, ?Throwable $error = null): void
{
    app()->instance(InboundFetcher::class, new class($messages, $error) implements InboundFetcher
    {
        /**
         * @param  list<InboundEmail>  $messages
         */
        public function __construct(private array $messages, private ?Throwable $error) {}

        /**
         * @return list<InboundEmail>
         */
        public function fetch(EmailMailbox $mailbox): array
        {
            if ($this->error !== null) {
                throw $this->error;
            }

            return $this->messages;
        }
    });
}

function inbox(string $uid, string $from, ?string $messageId = null, bool $read = false): InboundEmail
{
    return new InboundEmail(
        uid: $uid,
        messageId: $messageId,
        inReplyTo: null,
        references: null,
        fromEmail: $from,
        fromName: 'Client',
        to: [['email' => 'commercial@c2me.fr', 'name' => null]],
        cc: [],
        subject: 'Bonjour',
        textBody: 'Contenu',
        htmlBody: null,
        date: CarbonImmutable::now(),
        isRead: $read,
    );
}

function sharedMailbox(): EmailMailbox
{
    return EmailMailbox::factory()->create([
        'email' => 'commercial@c2me.fr',
        'imap_username' => 'commercial@c2me.fr',
        'smtp_username' => 'commercial@c2me.fr',
    ]);
}

test('la synchronisation importe les messages et les rattache au contact', function (): void {
    $account = CrmAccount::factory()->create(['book' => CrmBook::IndustrialClients]);
    $contact = CrmContact::factory()->create([
        'crm_account_id' => $account->id,
        'email' => 'jean@usine.fr',
    ]);

    $mailbox = sharedMailbox();

    bindFetcher([inbox('10', 'jean@usine.fr', 'abc@usine.fr')]);

    $imported = app(MailboxSynchronizer::class)->synchronize($mailbox);

    expect($imported)->toBe(1)
        ->and($mailbox->refresh()->last_uid)->toBe('10')
        ->and($mailbox->last_error)->toBeNull();

    $message = EmailMessage::query()->first();

    expect($message)->not->toBeNull()
        ->and($message->message_id)->toBe('abc@usine.fr')
        ->and($message->crm_contact_id)->toBe($contact->id)
        ->and($message->crm_account_id)->toBe($account->id);
});

test('la synchronisation déduplique les messages déjà importés', function (): void {
    $mailbox = sharedMailbox();

    bindFetcher([inbox('10', 'jean@usine.fr', 'abc@usine.fr')]);
    app(MailboxSynchronizer::class)->synchronize($mailbox);

    bindFetcher([inbox('10', 'jean@usine.fr', 'abc@usine.fr')]);
    $again = app(MailboxSynchronizer::class)->synchronize($mailbox);

    expect($again)->toBe(1)
        ->and(EmailMessage::query()->count())->toBe(1);
});

test('la synchronisation avance le curseur même pour un message ignoré', function (): void {
    $mailbox = sharedMailbox();
    $mailbox->forceFill(['last_uid' => '9'])->save();

    bindFetcher([inbox('10', '', 'sans-expediteur@usine.fr')]);

    app(MailboxSynchronizer::class)->synchronize($mailbox);

    expect($mailbox->refresh()->last_uid)->toBe('10')
        ->and(EmailMessage::query()->count())->toBe(0);
});

test('la synchronisation enregistre l’erreur en cas d’échec de connexion', function (): void {
    $mailbox = sharedMailbox();

    bindFetcher([], new RuntimeException('IMAP injoignable'));

    $imported = app(MailboxSynchronizer::class)->synchronize($mailbox);

    expect($imported)->toBe(0)
        ->and($mailbox->refresh()->last_error)->toBe('IMAP injoignable');
});

test('la commande mailboxes:sync importe les messages des boîtes actives', function (): void {
    $account = CrmAccount::factory()->create(['book' => CrmBook::IndustrialClients]);
    CrmContact::factory()->create([
        'crm_account_id' => $account->id,
        'email' => 'jean@usine.fr',
    ]);

    sharedMailbox();

    bindFetcher([inbox('42', 'jean@usine.fr', 'xyz@usine.fr')]);

    artisanCommand($this, 'mailboxes:sync')->assertSuccessful();

    expect(EmailMessage::query()->count())->toBe(1)
        ->and(EmailMessage::query()->first()->uid)->toBe('42');
});

test('la synchronisation enregistre les pièces jointes entrantes', function (): void {
    Storage::fake('local');

    $mailbox = sharedMailbox();

    bindFetcher([new InboundEmail(
        uid: '7',
        messageId: 'plan@usine.fr',
        inReplyTo: null,
        references: null,
        fromEmail: 'jean@usine.fr',
        fromName: 'Jean',
        to: [['email' => 'commercial@c2me.fr', 'name' => null]],
        cc: [],
        subject: 'Plan',
        textBody: 'Ci-joint le plan.',
        htmlBody: null,
        date: CarbonImmutable::now(),
        isRead: false,
        attachments: [
            new InboundAttachment('plan.pdf', 'application/pdf', 7, 'contenu'),
        ],
    )]);

    app(MailboxSynchronizer::class)->synchronize($mailbox);

    $attachment = EmailMessage::query()->firstOrFail()->attachments()->firstOrFail();

    expect($attachment->filename)->toBe('plan.pdf')
        ->and($attachment->mime_type)->toBe('application/pdf')
        ->and($attachment->size)->toBe(7)
        ->and(Storage::disk('local')->exists($attachment->path))->toBeTrue()
        ->and(Storage::disk('local')->get($attachment->path))->toBe('contenu');
});

test('une pièce jointe est téléchargeable par un utilisateur autorisé', function (): void {
    Storage::fake('local');

    $mailbox = sharedMailbox();
    $user = User::factory()->create();

    bindFetcher([new InboundEmail(
        uid: '8',
        messageId: 'pj@usine.fr',
        inReplyTo: null,
        references: null,
        fromEmail: 'jean@usine.fr',
        fromName: 'Jean',
        to: [],
        cc: [],
        subject: 'Plan',
        textBody: null,
        htmlBody: null,
        date: CarbonImmutable::now(),
        isRead: true,
        attachments: [
            new InboundAttachment('plan.pdf', 'application/pdf', 7, 'contenu'),
        ],
    )]);

    app(MailboxSynchronizer::class)->synchronize($mailbox);

    $attachment = EmailMessage::query()->firstOrFail()->attachments()->firstOrFail();

    $this->actingAs($user)
        ->get(route('emails.attachments.download', $attachment))
        ->assertOk()
        ->assertDownload('plan.pdf');
});

test('le premier import d une bo�te ne notifie pas les messages existants', function (): void {
    Event::fake([DatabaseNotificationsSent::class]);
    Notification::fake();

    $owner = User::factory()->create(['role' => UserRole::Commercial]);

    $mailbox = EmailMailbox::factory()->forUser($owner)->create([
        'last_uid' => null,
        'last_synced_at' => null,
    ]);

    bindFetcher([inbox('10', 'jean@usine.fr', 'premier@usine.fr')]);

    app(MailboxSynchronizer::class)->synchronize($mailbox);

    Notification::assertNothingSent();
});

test('un nouvel e-mail personnel notifie uniquement son propri�taire', function (): void {
    Event::fake([DatabaseNotificationsSent::class]);
    Notification::fake();

    $owner = User::factory()->create(['role' => UserRole::Commercial]);
    $other = User::factory()->create(['role' => UserRole::Commercial]);

    $mailbox = EmailMailbox::factory()->forUser($owner)->create([
        'last_uid' => '9',
        'last_synced_at' => now()->subMinute(),
    ]);

    bindFetcher([inbox('10', 'jean@usine.fr', 'nouveau@usine.fr')]);

    app(MailboxSynchronizer::class)->synchronize($mailbox);

    $message = EmailMessage::query()->firstOrFail();

    Notification::assertSentTo(
        $owner,
        InboundEmailReceived::class,
        fn (InboundEmailReceived $notification): bool => $notification->message->is($message),
    );
    Notification::assertNotSentTo($other, InboundEmailReceived::class);
    Event::assertDispatchedTimes(DatabaseNotificationsSent::class, 1);
});

test('un nouvel e-mail dans une bo�te partag�e notifie tous les utilisateurs actifs', function (): void {
    Event::fake([DatabaseNotificationsSent::class]);
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $commercial = User::factory()->create(['role' => UserRole::Commercial]);
    $inactive = User::factory()->create(['role' => UserRole::Commercial, 'is_active' => false]);

    $mailbox = sharedMailbox();
    $mailbox->forceFill(['last_uid' => '9', 'last_synced_at' => now()->subMinute()])->save();

    bindFetcher([inbox('10', 'jean@usine.fr', 'partage@usine.fr')]);

    app(MailboxSynchronizer::class)->synchronize($mailbox);

    Notification::assertSentTo([$admin, $commercial], InboundEmailReceived::class);
    Notification::assertNotSentTo($inactive, InboundEmailReceived::class);
});

test('un e-mail d�j� import� ou d�j� lu ne d�clenche pas de notification', function (): void {
    Event::fake([DatabaseNotificationsSent::class]);
    Notification::fake();

    $mailbox = sharedMailbox();
    $mailbox->forceFill(['last_uid' => '9', 'last_synced_at' => now()->subMinute()])->save();

    bindFetcher([inbox('10', 'jean@usine.fr', 'lu@usine.fr', read: true)]);
    app(MailboxSynchronizer::class)->synchronize($mailbox);

    bindFetcher([inbox('10', 'jean@usine.fr', 'lu@usine.fr', read: true)]);
    app(MailboxSynchronizer::class)->synchronize($mailbox);

    Notification::assertNothingSent();
});
