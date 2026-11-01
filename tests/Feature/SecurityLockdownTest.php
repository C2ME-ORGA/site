<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use MKWebDesign\FilamentWatchdog\Services\EmergencyLockdownService;
use Mockery;

test('un verrouillage non expiré n’est pas levé', function (): void {
    $service = Mockery::mock(EmergencyLockdownService::class);
    $service->shouldReceive('getLockdownStatus')->once()->andReturn([
        'lockdown_id' => 'emergency_1',
        'activated_at' => now()->subHour(),
    ]);
    $service->shouldReceive('deactivateEmergencyLockdown')->never();

    $this->app->instance(EmergencyLockdownService::class, $service);

    artisanCommand($this, 'security:lockdown:release')->assertExitCode(0);
});

test('un verrouillage expiré est levé automatiquement', function (): void {
    $service = Mockery::mock(EmergencyLockdownService::class);
    $service->shouldReceive('getLockdownStatus')->once()->andReturn([
        'lockdown_id' => 'emergency_1',
        'activated_at' => now()->subHours(25),
    ]);
    $service->shouldReceive('deactivateEmergencyLockdown')
        ->once()
        ->andReturn(['status' => 'success']);

    $this->app->instance(EmergencyLockdownService::class, $service);

    artisanCommand($this, 'security:lockdown:release')->assertExitCode(0);
});

test('sans verrouillage actif, la commande ne fait rien', function (): void {
    $service = Mockery::mock(EmergencyLockdownService::class);
    $service->shouldReceive('getLockdownStatus')->once()->andReturnNull();
    $service->shouldReceive('deactivateEmergencyLockdown')->never();

    $this->app->instance(EmergencyLockdownService::class, $service);

    artisanCommand($this, 'security:lockdown:release')->assertExitCode(0);
});

test('un échec de levée renvoie un code d’erreur', function (): void {
    $service = Mockery::mock(EmergencyLockdownService::class);
    $service->shouldReceive('getLockdownStatus')->once()->andReturn([
        'lockdown_id' => 'emergency_1',
        'activated_at' => now()->subDays(2),
    ]);
    $service->shouldReceive('deactivateEmergencyLockdown')
        ->once()
        ->andReturn(['status' => 'failed', 'error' => 'maintenance indisponible']);

    $this->app->instance(EmergencyLockdownService::class, $service);

    artisanCommand($this, 'security:lockdown:release')->assertExitCode(1);
});

test('le verrouillage est réservé aux administrateurs', function (): void {
    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    $this->actingAs($commercial)
        ->get('/admin/security/dashboard')
        ->assertForbidden();
});
