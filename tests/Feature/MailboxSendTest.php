<?php

use App\Mail\OutboundMail;
use App\Models\EmailMailbox;
use App\Services\Mail\MailboxMailer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function sendingMailbox(): EmailMailbox
{
    return EmailMailbox::factory()->create([
        'email' => 'commercial@c2me.fr',
        'imap_username' => 'commercial@c2me.fr',
        'smtp_username' => 'commercial@c2me.fr',
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

test('une réponse porte les en-têtes de fil et référence le message d’origine', function (): void {
    Mail::fake();

    $message = app(MailboxMailer::class)->send(
        sendingMailbox(),
        ['client@usine.fr'],
        'Re: Votre devis',
        '<p>Merci</p>',
        inReplyTo: '<original@c2me.fr>',
        references: ['<premier@c2me.fr>', '<original@c2me.fr>'],
    );

    Mail::assertSent(OutboundMail::class, fn (OutboundMail $mail): bool => $mail->inReplyTo === '<original@c2me.fr>'
        && $mail->references === ['<premier@c2me.fr>', '<original@c2me.fr>']);

    expect($message->in_reply_to)->toBe('<original@c2me.fr>')
        ->and($message->references)->toBe('<premier@c2me.fr> <original@c2me.fr>');
});

test('les pièces jointes sont envoyées puis supprimées du disque', function (): void {
    Mail::fake();
    Storage::fake('local');

    Storage::disk('local')->put('mail-attachments/devis.pdf', 'contenu');

    app(MailboxMailer::class)->send(
        sendingMailbox(),
        ['client@usine.fr'],
        'Votre devis',
        '<p>Bonjour</p>',
        attachments: ['mail-attachments/devis.pdf'],
    );

    Mail::assertSent(OutboundMail::class, fn (OutboundMail $mail): bool => count($mail->attachments()) === 1);

    expect(Storage::disk('local')->exists('mail-attachments/devis.pdf'))->toBeFalse();
});
