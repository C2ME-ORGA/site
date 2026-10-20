<?php

use App\Mail\OutboundMail;
use App\Models\EmailMailbox;
use App\Services\Mail\MailboxMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function sendingMailbox(): EmailMailbox
{
    return EmailMailbox::create([
        'name' => 'Commercial',
        'email' => 'commercial@c2me.fr',
        'is_shared' => true,
        'imap_host' => 'imap.orange.fr',
        'imap_port' => 993,
        'imap_encryption' => 'ssl',
        'imap_username' => 'commercial@c2me.fr',
        'imap_password' => 'secret',
        'smtp_host' => 'smtp.orange.fr',
        'smtp_port' => 465,
        'smtp_encryption' => 'ssl',
        'smtp_username' => 'commercial@c2me.fr',
        'smtp_password' => 'secret',
        'is_active' => true,
    ]);
}

test('l’envoi expédie le message et l’archive en sortant', function (): void {
    Mail::fake();

    $mailbox = sendingMailbox();

    $message = app(MailboxMailer::class)->send(
        $mailbox,
        ['client@usine.fr'],
        'Votre devis',
        '<p>Bonjour</p>',
        ['copie@usine.fr'],
    );

    Mail::assertSent(OutboundMail::class, fn (OutboundMail $mail): bool => $mail->hasTo('client@usine.fr')
        && $mail->hasCc('copie@usine.fr')
        && $mail->mailSubject === 'Votre devis');

    expect($message->is_sent)->toBeTrue()
        ->and($message->from_email)->toBe('commercial@c2me.fr')
        ->and($message->folder)->toBe('Sent')
        ->and($message->to_recipients)->toBe([['email' => 'client@usine.fr', 'name' => null]]);
});
