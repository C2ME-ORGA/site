<?php

use App\Enums\UserRole;
use App\Filament\Resources\Emails\Pages\ViewEmailMessage;
use App\Mail\OutboundMail;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function composeAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
}

function composeMailbox(): EmailMailbox
{
    return EmailMailbox::factory()->create([
        'email' => 'commercial@c2me.fr',
        'imap_username' => 'commercial@c2me.fr',
        'smtp_username' => 'commercial@c2me.fr',
        'is_shared' => true,
    ]);
}

test('la fenêtre de composition envoie et archive le message', function (): void {
    Mail::fake();

    $admin = composeAdmin();
    $mailbox = composeMailbox();

    $source = EmailMessage::factory()->create([
        'email_mailbox_id' => $mailbox->id,
        'from_email' => 'jean@usine.fr',
        'subject' => 'Plan',
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewEmailMessage::class, ['record' => $source->getRouteKey()])
        ->callAction('compose', data: [
            'mailbox_id' => $mailbox->id,
            'to' => 'client@usine.fr',
            'subject' => 'Réponse au plan',
            'body' => 'Bonjour, voici les précisions.',
        ])
        ->assertHasNoActionErrors();

    Mail::assertSent(OutboundMail::class, fn (OutboundMail $mail): bool => $mail->hasTo('client@usine.fr')
        && $mail->mailSubject === 'Réponse au plan');

    expect(EmailMessage::query()->where('is_sent', true)->where('subject', 'Réponse au plan')->exists())->toBeTrue();
});

test('la composition valide les champs obligatoires', function (): void {
    Mail::fake();

    $admin = composeAdmin();
    $mailbox = composeMailbox();

    $source = EmailMessage::factory()->create(['email_mailbox_id' => $mailbox->id]);

    $this->actingAs($admin);

    Livewire::test(ViewEmailMessage::class, ['record' => $source->getRouteKey()])
        ->callAction('compose', data: [
            'mailbox_id' => $mailbox->id,
            'to' => '',
            'subject' => '',
            'body' => '',
        ])
        ->assertHasActionErrors(['to', 'subject', 'body']);

    Mail::assertNothingSent();
});

test('la réponse porte les en-têtes de fil du message d’origine', function (): void {
    Mail::fake();

    $admin = composeAdmin();
    $mailbox = composeMailbox();

    $source = EmailMessage::factory()->create([
        'email_mailbox_id' => $mailbox->id,
        'message_id' => 'orig@usine.fr',
        'from_email' => 'jean@usine.fr',
        'subject' => 'Plan',
        'body_text' => 'Bonjour',
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewEmailMessage::class, ['record' => $source->getRouteKey()])
        ->callAction('reply', data: [
            'mailbox_id' => $mailbox->id,
            'to' => 'jean@usine.fr',
            'subject' => 'Re: Plan',
            'body' => 'Merci pour votre message.',
        ])
        ->assertHasNoActionErrors();

    Mail::assertSent(OutboundMail::class, fn (OutboundMail $mail): bool => $mail->inReplyTo === 'orig@usine.fr'
        && in_array('orig@usine.fr', $mail->references, true));
});

test('la composition joint un fichier et le supprime après envoi', function (): void {
    Mail::fake();
    Storage::fake('local');

    Storage::disk('local')->put('mail-attachments/plan.pdf', 'contenu');

    $admin = composeAdmin();
    $mailbox = composeMailbox();

    $source = EmailMessage::factory()->create(['email_mailbox_id' => $mailbox->id]);

    $this->actingAs($admin);

    Livewire::test(ViewEmailMessage::class, ['record' => $source->getRouteKey()])
        ->callAction('compose', data: [
            'mailbox_id' => $mailbox->id,
            'to' => 'client@usine.fr',
            'subject' => 'Plan',
            'body' => 'Ci-joint.',
            'attachments' => ['mail-attachments/plan.pdf'],
        ])
        ->assertHasNoActionErrors();

    Mail::assertSent(OutboundMail::class, fn (OutboundMail $mail): bool => count($mail->attachments()) === 1);

    expect(Storage::disk('local')->exists('mail-attachments/plan.pdf'))->toBeFalse();
});
