<?php

use App\Enums\UserRole;
use App\Filament\Pages\EditProfile;
use App\Filament\Resources\Emails\Pages\ViewEmailMessage;
use App\Models\EmailMessage;
use App\Models\User;
use App\Support\Mail\EmailBody;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Livewire\Livewire;

/**
 * Compte disposant d'un accès au back-office et d'une signature optionnelle.
 */
function signatureUser(?string $signature = null): User
{
    return User::factory()->create([
        'email' => 'auteur@test.local',
        'role' => UserRole::Admin,
        'is_active' => true,
        'email_signature' => $signature,
    ]);
}

function signatureMessage(): EmailMessage
{
    return EmailMessage::factory()->create([
        'from_email' => 'client@test.local',
        'from_name' => 'Alice Client',
        'subject' => 'Question sur le devis',
        'body_text' => "Bonjour,\nPouvez-vous me confirmer le délai ?",
        'received_at' => '2026-10-30 10:15:00',
    ]);
}

function signatureHtml(): string
{
    return '<p><strong>Cordialement,</strong><br>Camille Martin<br><img src="/storage/email-signatures/logo.png" alt="C2ME"></p>';
}

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

test('le profil expose un éditeur enrichi pour la signature e-mail', function (): void {
    $this->actingAs(signatureUser())
        ->get('/admin/profile')
        ->assertOk()
        ->assertSee('Signature e-mail')
        ->assertSee('Vous pouvez la mettre en forme et y insérer des images.')
        ->assertSee('fi-fo-rich-editor', escape: false);
});

test('les images de signature sont limitées aux images et isolées par utilisateur', function (): void {
    $user = signatureUser();

    $this->actingAs($user);

    $page = Livewire::test(EditProfile::class)->instance();

    if (! $page instanceof EditProfile) {
        throw new RuntimeException('La page de profil n’a pas pu être montée.');
    }

    $component = $page->getSchema('form')?->getComponent('email_signature');

    if (! $component instanceof RichEditor) {
        throw new RuntimeException('Le champ de signature n’est pas un éditeur enrichi.');
    }

    expect($component->getFileAttachmentsDirectory())->toBe('email-signatures/'.$user->id)
        ->and($component->getFileAttachmentsAcceptedFileTypes())->toBe([
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ]);
});

test('la signature e-mail enrichie est enregistrée depuis le profil', function (): void {
    $user = signatureUser();
    $signature = signatureHtml();

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->set('data.email_signature', $signature)
        ->call('save')
        ->assertHasNoErrors();

    $saved = (string) $user->fresh()->email_signature;

    expect($saved)->toContain('<strong>Cordialement,</strong>')
        ->and($saved)->toContain('<img')
        ->and($saved)->toContain('/storage/email-signatures/logo.png');
});

test('les images du corps sont limitées aux images et isolées par utilisateur', function (): void {
    $user = signatureUser();
    $message = signatureMessage();

    $test = Livewire::actingAs($user)
        ->test(ViewEmailMessage::class, ['record' => $message->id])
        ->mountAction('compose');

    $instance = $test->instance();

    if (! $instance instanceof ViewEmailMessage) {
        throw new RuntimeException('La page du message n’a pas pu être montée.');
    }

    $schemaName = $instance->getMountedActionSchemaName();

    if ($schemaName === null) {
        throw new RuntimeException('L’action de composition n’a pas été montée.');
    }

    $component = $instance->getSchema($schemaName)?->getComponent('body');

    if (! $component instanceof RichEditor) {
        throw new RuntimeException('Le corps du message n’est pas un éditeur enrichi.');
    }

    expect($component->getFileAttachmentsDirectory())->toBe('mail-body/'.$user->id)
        ->and($component->getFileAttachmentsAcceptedFileTypes())->toBe([
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ]);
});

test('un nouveau message est prérempli avec la signature enrichie', function (): void {
    $user = signatureUser(signatureHtml());
    $message = signatureMessage();

    Livewire::actingAs($user)
        ->test(ViewEmailMessage::class, ['record' => $message->id])
        ->mountAction('compose')
        ->assertSchemaStateSet([
            'body' => signatureHtml(),
        ]);
});

test('une réponse place la signature avant le texte cité', function (): void {
    $user = signatureUser(signatureHtml());
    $message = signatureMessage();

    Livewire::actingAs($user)
        ->test(ViewEmailMessage::class, ['record' => $message->id])
        ->mountAction('reply')
        ->assertSchemaStateSet([
            'body' => signatureHtml()
                .'<p><br></p><blockquote><p>Le 30/10/2026 10:15, Alice Client &lt;client@test.local&gt; a écrit :</p>'
                .'<p>Bonjour,<br>Pouvez-vous me confirmer le délai ?</p></blockquote>',
        ]);
});

test('sans signature le corps du message reste inchangé', function (): void {
    $user = signatureUser();
    $message = signatureMessage();

    Livewire::actingAs($user)
        ->test(ViewEmailMessage::class, ['record' => $message->id])
        ->mountAction('compose')
        ->assertSchemaStateSet(['body' => '<p></p>']);

    Livewire::actingAs($user)
        ->test(ViewEmailMessage::class, ['record' => $message->id])
        ->mountAction('reply')
        ->assertSchemaStateSet([
            'body' => '<p><br></p><blockquote><p>Le 30/10/2026 10:15, Alice Client &lt;client@test.local&gt; a écrit :</p>'
                .'<p>Bonjour,<br>Pouvez-vous me confirmer le délai ?</p></blockquote>',
        ]);
});

test('les images et liens relatifs deviennent absolus pour l’envoi', function (): void {
    $html = '<p><img src="/storage/email-signatures/logo.png" alt="C2ME"></p>'
        .'<p><a href="/contact">Nous contacter</a></p>'
        .'<p><a href="https://c2me.fr">Site</a></p>';

    $result = EmailBody::absoluteUrls($html);

    expect($result)
        ->toContain('src="'.url('/storage/email-signatures/logo.png').'"')
        ->toContain('href="'.url('/contact').'"')
        ->toContain('href="https://c2me.fr"');
});
