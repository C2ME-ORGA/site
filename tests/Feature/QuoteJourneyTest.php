<?php

use App\Enums\QuoteKind;

test('la page devis présente les deux parcours', function (): void {
    $this->get(route('quote'))
        ->assertOk()
        ->assertSeeInOrder([
            QuoteKind::Laser->getLabel(),
            QuoteKind::Building->getLabel(),
        ])
        ->assertSee(route('quote.laser'), false)
        ->assertSee(route('quote.building'), false);
});

test('le parcours laser est joignable directement', function (): void {
    $this->get(route('quote.laser'))
        ->assertOk()
        ->assertSee('Votre projet de découpe laser', false);
});

test('le parcours bâtiment est joignable directement', function (): void {
    $this->get(route('quote.building'))
        ->assertOk()
        ->assertSee('Votre projet de bâtiment', false);
});

test('la fiche découpe laser pointe vers son parcours', function (): void {
    $this->get(route('services.laser'))
        ->assertOk()
        ->assertSee(route('quote.laser'), false)
        ->assertDontSee(route('quote.building'), false);
});

test('la fiche charpente pointe vers le parcours bâtiment', function (): void {
    $this->get(route('services.frames'))
        ->assertOk()
        ->assertSee(route('quote.building'), false)
        ->assertDontSee(route('quote.laser'), false);
});

test('la fiche ingénierie rejoint le sélecteur de devis', function (): void {
    $this->get(route('services.design'))
        ->assertOk()
        ->assertSee(route('quote'), false);
});
