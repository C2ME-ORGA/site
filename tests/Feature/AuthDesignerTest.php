<?php

namespace Tests\Feature;

use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Pages\Auth\Login;
use Filament\Facades\Filament;

test('le plugin de connexion est enregistré sur les deux panneaux', function (string $panel): void {
    $panel = Filament::getPanel($panel);

    expect($panel->hasPlugin(AuthDesignerPlugin::make()->getId()))->toBeTrue()
        ->and($panel->getLoginRouteAction())->toBe(Login::class);
})->with(['admin', 'knowledge-base']);

test('la page de connexion reprend la photo et les couleurs de l’entreprise', function (): void {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSee('login-bg.webp')
        ->assertSee('media-left')
        ->assertSee('Espace administration')
        ->assertSee('fi-auth-theme-switcher');
});

test('la page de connexion de la base documentaire est habillée de la même façon', function (): void {
    $this->get('/aide/login')
        ->assertOk()
        ->assertSee('login-bg.webp')
        ->assertSee('media-left')
        ->assertSee('Base documentaire');
});

test('les pages protégées renvoient toujours vers la connexion', function (): void {
    $this->get('/admin')->assertRedirect();
    $this->get('/aide')->assertRedirect();
});
