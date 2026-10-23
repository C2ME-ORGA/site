<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Boquizo\FilamentLogViewer\FilamentLogViewerPlugin;
use Filament\Facades\Filament;

function logViewerAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function logViewerCommercial(): User
{
    return User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
}

test('la visionneuse de logs est enregistrée sur le panel avec son thème', function (): void {
    $panel = Filament::getPanel('admin');

    expect($panel->hasPlugin(FilamentLogViewerPlugin::ID))->toBeTrue()
        ->and($panel->getViteTheme())->toBe('resources/css/filament/admin/theme.css');
});

test('un administrateur accède à la visionneuse de logs', function (): void {
    $admin = logViewerAdmin();

    $this->actingAs($admin)
        ->get('/admin/logs')
        ->assertOk()
        ->assertSee('Visionneuse de logs');
});

test('l’entrée de navigation est visible pour l’administrateur', function (): void {
    $this->actingAs(logViewerAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Visionneuse de logs');
});

test('l’entrée de navigation est masquée pour le commercial', function (): void {
    $this->actingAs(logViewerCommercial())
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('Visionneuse de logs');
});

test('un commercial n’accède pas à la visionneuse de logs', function (): void {
    $this->actingAs(logViewerCommercial())
        ->get('/admin/logs')
        ->assertForbidden();
});

test('un visiteur non connecté est redirigé vers la connexion', function (): void {
    $this->get('/admin/logs')
        ->assertRedirect();
});

test('la consultation d’un journal inexistant renvoie une erreur 404', function (): void {
    $this->actingAs(logViewerAdmin())
        ->get('/admin/logs/1970-01-01')
        ->assertNotFound();
});
