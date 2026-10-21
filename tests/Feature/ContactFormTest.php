<?php

use App\Mail\ContactRequestReceived;
use App\Models\ContactRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    // Les tests partagent la même IP (127.0.0.1) : on repart d'un compteur vierge.
    RateLimiter::clear('contact-form:127.0.0.1');
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, string>
 */
function validContactPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Jean Dupont',
        'company' => 'Charpentes Dupont',
        'email' => 'jean@dupont.fr',
        'phone' => '06 12 34 56 78',
        'requestType' => ContactRequest::requestTypes()[0],
        'message' => 'Nous souhaitons un devis pour 40 pièces découpées en acier 3 mm.',
        'website' => '',
    ], $overrides);
}

test('la page contact affiche le formulaire', function (): void {
    $this->get(route('contact'))
        ->assertOk()
        ->assertSee('id="contact-name"', false)
        ->assertSee('id="contact-message"', false);
});

test('une demande valide est enregistrée et notifiée par e-mail', function (): void {
    Mail::fake();

    Livewire::test('contact-form')
        ->set(validContactPayload())
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $this->assertDatabaseHas('contact_requests', [
        'name' => 'Jean Dupont',
        'company' => 'Charpentes Dupont',
        'email' => 'jean@dupont.fr',
        'phone' => '06 12 34 56 78',
        'request_type' => ContactRequest::requestTypes()[0],
    ]);

    Mail::assertSent(
        ContactRequestReceived::class,
        fn (ContactRequestReceived $mail): bool => $mail->hasTo(config('site.company.email'))
            && $mail->contact->email === 'jean@dupont.fr'
    );
});

test('le formulaire se réinitialise après envoi', function (): void {
    Mail::fake();

    Livewire::test('contact-form')
        ->set(validContactPayload())
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('name', '')
        ->assertSet('email', '')
        ->assertSet('message', '');
});

test('les champs obligatoires sont validés', function (): void {
    Livewire::test('contact-form')
        ->set(validContactPayload([
            'name' => '',
            'email' => 'pas-un-email',
            'message' => '',
            'requestType' => '',
        ]))
        ->call('submit')
        ->assertHasErrors([
            'name' => 'required',
            'email' => 'email',
            'message' => 'required',
            'requestType' => 'required',
        ]);

    $this->assertDatabaseCount('contact_requests', 0);
});

test('un type de demande inconnu est refusé', function (): void {
    Livewire::test('contact-form')
        ->set(validContactPayload(['requestType' => 'Pisciniste']))
        ->call('submit')
        ->assertHasErrors(['requestType' => 'in']);

    $this->assertDatabaseCount('contact_requests', 0);
});

test('le pot de miel avale les robots sans enregistrer la demande', function (): void {
    Mail::fake();

    Livewire::test('contact-form')
        ->set(validContactPayload(['website' => 'https://spam.example']))
        ->call('submit')
        // Fausse réussite : le bot ne doit pas apprendre qu'il a été détecté.
        ->assertSet('submitted', true);

    $this->assertDatabaseCount('contact_requests', 0);
    Mail::assertNothingSent();
});

test('les soumissions sont limitées à trois par heure et par adresse', function (): void {
    Mail::fake();

    foreach (range(1, 3) as $attempt) {
        Livewire::test('contact-form')
            ->set(validContactPayload(['email' => "client{$attempt}@exemple.fr"]))
            ->call('submit')
            ->assertHasNoErrors();
    }

    $this->assertDatabaseCount('contact_requests', 3);

    Livewire::test('contact-form')
        ->set(validContactPayload())
        ->call('submit')
        ->assertHasErrors(['message']);

    $this->assertDatabaseCount('contact_requests', 3);
});

test('un échec d envoi d e-mail n empêche pas d enregistrer la demande', function (): void {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP indisponible'));

    Livewire::test('contact-form')
        ->set(validContactPayload())
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $this->assertDatabaseCount('contact_requests', 1);
});

test('le type de demande se pré-remplit depuis la page d origine', function (): void {
    Livewire::withHeaders(['Referer' => route('services.laser')])
        ->test('contact-form')
        ->assertSet('requestType', 'Découpe laser');
});

test('le type de demande reste vide hors d une fiche de service', function (): void {
    Livewire::withHeaders(['Referer' => route('projects')])
        ->test('contact-form')
        ->assertSet('requestType', '');

    Livewire::test('contact-form')
        ->assertSet('requestType', '');
});
