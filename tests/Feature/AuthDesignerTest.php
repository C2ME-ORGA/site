<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Caresome\FilamentAuthDesigner\AuthDesignerPlugin;
use Caresome\FilamentAuthDesigner\Pages\Auth\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;

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

test('un échec de connexion ne révèle pas l’existence du compte', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    User::factory()->create([
        'email' => 'existe@example.test',
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);

    $unknown = Livewire::test(Login::class)
        ->fillForm(['email' => 'inconnu@example.test', 'password' => 'mot-de-passe'])
        ->call('authenticate');

    $known = Livewire::test(Login::class)
        ->fillForm(['email' => 'existe@example.test', 'password' => 'mauvais-mot-de-passe'])
        ->call('authenticate');

    $unknownMessage = $unknown->errors()->first('data.email');

    expect($unknownMessage)->not->toBeEmpty()
        ->and($unknownMessage)->toBe($known->errors()->first('data.email'));
});
