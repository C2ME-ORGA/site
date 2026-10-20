<?php

use App\Contracts\InboundFetcher;
use App\Enums\CrmBook;
use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Models\User;
use App\Services\MailboxSynchronizer;
use App\Support\Mail\InboundAttachment;
use App\Support\Mail\InboundEmail;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

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

    $this->artisan('mailboxes:sync')->assertSuccessful();

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
