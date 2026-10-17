<?php

use App\Enums\QuoteKind;
use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    RateLimiter::clear('quote-building:127.0.0.1');
});

/**
 * Franchit les étapes 1 à 3 du parcours bâtiment et s'arrête sur l'étape 4.
 *
 * @return Testable
 */
function buildingAtStepFour()
{
    return Livewire::test('building-quote')
        ->set('type', 'industriel')
        ->set('usage', 'production')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->set('city', 'Montaigu-Vendée')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->set('envelope', 'bardage')
        ->call('togglePrestation', 'etude')
        ->call('togglePrestation', 'montage')
        ->call('nextStep')
        ->assertHasNoErrors();
}

test('la page du parcours bâtiment affiche la première étape', function (): void {
    $this->get(route('quote.building'))
        ->assertOk()
        ->assertSee('Votre projet de bâtiment', false)
        ->assertSee('Nature du bâtiment', false);
});

test('on ne peut pas avancer sans nature de bâtiment ni usage', function (): void {
    Livewire::test('building-quote')
        ->call('nextStep')
        ->assertHasErrors(['type', 'usage'])
        ->assertSet('step', 1);
});

test('la commune du chantier est obligatoire', function (): void {
    Livewire::test('building-quote')
        ->set('type', 'agricole')
        ->set('usage', 'stockage')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->call('nextStep')
        ->assertHasErrors(['city'])
        ->assertSet('step', 2);
});

test("le niveau de livraison conditionne l'enveloppe", function (): void {
    Livewire::test('building-quote')
        ->set('type', 'agricole')
        ->set('usage', 'stockage')
        ->call('nextStep')
        ->set('city', 'La Roche-sur-Yon')
        ->call('nextStep')
        ->call('nextStep')
        ->assertHasErrors(['envelope'])
        ->assertSet('step', 3);
});

test('les prestations se cochent et se décochent', function (): void {
    Livewire::test('building-quote')
        ->call('togglePrestation', 'etude')
        ->call('togglePrestation', 'montage')
        ->assertSet('prestations', ['etude', 'montage'])
        ->call('togglePrestation', 'etude')
        ->assertSet('prestations', ['montage']);
});

test('on ne peut pas sauter une étape du parcours', function (): void {
    Livewire::test('building-quote')
        ->call('goToStep', 6)
        ->assertSet('step', 1);
});

test('une enveloppe budgétaire non renseignée est refusée', function (): void {
    buildingAtStepFour()
        ->set('budget', '')
        ->call('nextStep')
        ->assertHasErrors(['budget'])
        ->assertSet('step', 4);
});

test('un bâtiment complet enregistre le brief et notifie le bureau', function (): void {
    Storage::fake('local');
    Mail::fake();

    buildingAtStepFour()
        ->set('delay', 'moins-6m')
        ->set('budget', '150-400k')
        ->set('level', 'bureau')
        ->set('length', 30)
        ->set('width', 20)
        ->set('height', 8)
        ->call('nextStep')
        ->assertHasNoErrors()
        ->set('plans', [UploadedFile::fake()->create('plan-de-masse.pdf', 300)])
        ->call('nextStep')
        ->assertHasNoErrors()
        ->set('name', 'Jean Dupont')
        ->set('company', 'Charpentes Dupont')
        ->set('email', 'jean@dupont.fr')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $quote = QuoteRequest::first();

    expect($quote)->not->toBeNull()
        ->and($quote->kind)->toBe(QuoteKind::Building)
        ->and($quote->city)->toBe('Montaigu-Vendée')
        ->and($quote->files)->toHaveCount(1);

    expect($quote->payload)
        ->toHaveKey('Type de bâtiment', 'Bâtiment industriel')
        ->toHaveKey('Usage', 'Production / atelier')
        ->toHaveKey('Commune', 'Montaigu-Vendée')
        ->toHaveKey('Dimensions', 'L 30 m · l 20 m · H 8 m')
        ->toHaveKey('Enveloppe', 'Structure + bardage')
        ->toHaveKey('Prestations', 'Dessin & ingénierie · Montage sur site')
        ->toHaveKey('Calendrier', 'Sous 6 mois')
        ->toHaveKey('Budget', 'De 150 000 à 400 000 €')
        ->toHaveKey('Vous êtes', 'Architecte / bureau d\'études');

    expect($quote->files->first()->path)
        ->toStartWith('quote-requests/');

    Mail::assertSent(
        QuoteRequestReceived::class,
        fn (QuoteRequestReceived $mail): bool => $mail->hasTo(config('site.company.email'))
            && $mail->quote->kind === QuoteKind::Building
    );
});

test('le pot de miel dissimulé détourne le robot sans rien enregistrer', function (): void {
    Mail::fake();

    Livewire::test('building-quote')
        ->set('website', 'https://spam.example')
        ->call('submit')
        ->assertSet('submitted', true);

    $this->assertDatabaseCount('quote_requests', 0);

    Mail::assertNothingSent();
});

test('le parcours limite le nombre de demandes par adresse', function (): void {
    config(['site.quote.rate_limit' => 1]);

    $payload = [
        'type' => 'agricole',
        'usage' => 'stockage',
        'city' => 'La Roche-sur-Yon',
        'envelope' => 'structure',
        'delay' => 'inconnu',
        'budget' => 'inconnu',
        'level' => 'particulier',
        'plansLater' => true,
        'name' => 'Jean Dupont',
        'email' => 'jean@dupont.fr',
    ];

    Livewire::test('building-quote')
        ->set($payload)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    Livewire::test('building-quote')
        ->set($payload)
        ->call('submit')
        ->assertHasErrors(['message']);

    $this->assertDatabaseCount('quote_requests', 1);
});
