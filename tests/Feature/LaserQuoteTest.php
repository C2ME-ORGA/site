<?php

use App\Enums\QuoteKind;
use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    // Les tests partagent la même IP (127.0.0.1) : on repart d'un compteur vierge.
    RateLimiter::clear('quote-laser:127.0.0.1');
});

test('la page du parcours laser affiche la première étape', function (): void {
    $this->get(route('quote.laser'))
        ->assertOk()
        ->assertSee('Votre projet de découpe laser', false)
        ->assertSee('Matière', false);
});

test('on ne peut pas avancer sans matière ni quantité', function (): void {
    Livewire::test('laser-quote')
        ->call('nextStep')
        ->assertHasErrors(['material', 'thickness', 'quantity'])
        ->assertSet('step', 1);
});

test('une nouvelle matière réinitialise la plage de découpes', function (): void {
    Livewire::test('laser-quote')
        ->set('material', 'acier')
        ->set('thickness', 30)
        ->set('material', 'aluminium')
        ->assertSet('thickness', null);
});

test('une épaisseur hors capacité de la matière est refusée', function (): void {
    Livewire::test('laser-quote')
        ->set('material', 'aluminium')
        ->set('thickness', 30)
        ->set('quantity', 'unitaire')
        ->call('nextStep')
        ->assertHasErrors(['thickness']);
});

test('on ne peut pas aller en avant dans le parcours', function (): void {
    Livewire::test('laser-quote')
        ->call('goToStep', 4)
        ->assertSet('step', 1);
});

test('on peut revenir à une étape déjà franchie', function (): void {
    Livewire::test('laser-quote')
        ->set('step', 3)
        ->call('goToStep', 1)
        ->assertSet('step', 1);
});

test('un plan au format refusé est écarté', function (): void {
    Storage::fake('local');

    Livewire::test('laser-quote')
        ->set('material', 'acier')
        ->set('thickness', 3)
        ->set('quantity', 'unitaire')
        ->call('nextStep')
        ->set('complexity', 'simple')
        ->set('delay', 'standard')
        ->set('delivery', 'retrait')
        ->call('nextStep')
        ->set('plans', [UploadedFile::fake()->create('virus.exe', 10)])
        ->call('nextStep')
        ->assertHasErrors(['plans.0']);
});

test('sans plan, le visiteur peut confirmer en transmettre plus tard', function (): void {
    Livewire::test('laser-quote')
        ->set('material', 'acier')
        ->set('thickness', 3)
        ->set('quantity', 'unitaire')
        ->call('nextStep')
        ->set('complexity', 'simple')
        ->set('delay', 'standard')
        ->set('delivery', 'retrait')
        ->call('nextStep')
        ->call('nextStep')
        ->assertHasErrors(['plansLater']);
});

test('une demande laser complète enregistre la demande et les plans', function (): void {
    Storage::fake('local');
    Mail::fake();

    Livewire::test('laser-quote')
        ->set('material', 'acier')
        ->set('thickness', 3)
        ->set('quantity', 'moins-1t')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->set('complexity', 'simple')
        ->set('length', 500)
        ->set('width', 300)
        ->set('delay', 'urgent')
        ->set('delivery', 'livraison')
        ->set('city', 'La Roche-sur-Yon')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->set('plans', [UploadedFile::fake()->create('pieces.dxf', 120)])
        ->call('nextStep')
        ->assertHasNoErrors()
        ->set('name', 'Jean Dupont')
        ->set('company', 'Charpentes Dupont')
        ->set('email', 'jean@dupont.fr')
        ->set('phone', '06 12 34 56 78')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $quote = QuoteRequest::first();

    expect($quote)->not->toBeNull()
        ->and($quote->kind)->toBe(QuoteKind::Laser)
        ->and($quote->email)->toBe('jean@dupont.fr')
        ->and($quote->city)->toBe('La Roche-sur-Yon')
        ->and($quote->status->value)->toBe('new');

    expect($quote->payload)
        ->toHaveKey('Matière', 'Acier')
        ->toHaveKey('Épaisseur', '3 mm')
        ->toHaveKey('Quantité', 'Série inférieure à 1 tonne')
        ->toHaveKey('Type de pièces', 'Pièces simples')
        ->toHaveKey('Dimensions', '500 × 300 mm')
        ->toHaveKey('Délai', 'Urgent')
        ->toHaveKey('Livraison', 'Livraison — La Roche-sur-Yon');

    expect($quote->files)->toHaveCount(1);

    $file = $quote->files->first();

    expect($file->original_name)->toBe('pieces.dxf')
        ->and($file->exists())->toBeTrue();

    // Le plan ne doit jamais atterrir dans un dossier public.
    Storage::disk('local')->assertExists($file->path);
    expect(str_starts_with($file->path, 'quote-requests/'))->toBeTrue();

    Mail::assertSent(
        QuoteRequestReceived::class,
        fn (QuoteRequestReceived $mail): bool => $mail->hasTo(config('site.company.email'))
            && $mail->quote->email === 'jean@dupont.fr'
    );
});

test('le pot de miel dissimulé détourne le robot sans rien enregistrer', function (): void {
    Mail::fake();

    Livewire::test('laser-quote')
        ->set('website', 'https://spam.example')
        ->call('submit')
        ->assertSet('submitted', true);

    $this->assertDatabaseCount('quote_requests', 0);

    Mail::assertNothingSent();
});

test('le parcours limite le nombre de demandes par adresse', function (): void {
    config(['site.quote.rate_limit' => 1]);

    $payload = [
        'material' => 'acier',
        'thickness' => 3,
        'quantity' => 'unitaire',
        'complexity' => 'simple',
        'delay' => 'standard',
        'delivery' => 'retrait',
        'plansLater' => true,
        'name' => 'Jean Dupont',
        'email' => 'jean@dupont.fr',
    ];

    Livewire::test('laser-quote')
        ->set($payload)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    Livewire::test('laser-quote')
        ->set($payload)
        ->call('submit')
        ->assertHasErrors(['message']);

    $this->assertDatabaseCount('quote_requests', 1);
});
