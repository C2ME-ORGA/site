<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use MKWebDesign\FilamentWatchdog\Models\ActivityLog;
use MKWebDesign\FilamentWatchdog\Models\SecurityAlert;
use MKWebDesign\FilamentWatchdog\Resources\SecurityAlertResource;
use MKWebDesign\FilamentWatchdog\Widgets\RecentAlertsWidget;
use MKWebDesign\FilamentWatchdog\Widgets\SecurityOverviewWidget;
use MKWebDesign\FilamentWatchdog\Widgets\ThreatLevelWidget;

function watchdogAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function watchdogCommercial(): User
{
    return User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
}

test('un administrateur accède au tableau de bord de sécurité', function (): void {
    $this->actingAs(watchdogAdmin())
        ->get('/admin/security/dashboard')
        ->assertOk()
        ->assertSee('Sécurité')
        ->assertSee('System Status');
});

test('un commercial n’accède pas au tableau de bord de sécurité', function (): void {
    $this->actingAs(watchdogCommercial())
        ->get('/admin/security/dashboard')
        ->assertForbidden();
});

test('un visiteur non connecté est redirigé vers la connexion', function (): void {
    $this->get('/admin/security/dashboard')->assertRedirect();
});

test('les listes de sécurité sont réservées aux administrateurs', function (string $url): void {
    $this->actingAs(watchdogAdmin())->get($url)->assertOk();

    $this->actingAs(watchdogCommercial())->get($url)->assertForbidden();
})->with([
    'alertes' => ['/admin/security-alerts'],
    'intégrité' => ['/admin/file-integrities'],
    'malwares' => ['/admin/malware-detections'],
    'activité' => ['/admin/activity-logs'],
]);

test('les écrans de sécurité sont en lecture seule', function (): void {
    $admin = watchdogAdmin();
    $alert = new SecurityAlert;

    expect(Gate::forUser($admin)->allows('viewAny', SecurityAlert::class))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('create', SecurityAlert::class))->toBeFalse()
        ->and(SecurityAlertResource::canCreate())->toBeFalse()
        ->and(SecurityAlertResource::canEdit($alert))->toBeFalse()
        ->and(SecurityAlertResource::canDelete($alert))->toBeFalse();
});

test('l’entrée de navigation est visible pour l’administrateur', function (): void {
    $this->actingAs(watchdogAdmin())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Sécurité');
});

test('l’entrée de navigation est masquée pour le commercial', function (): void {
    $this->actingAs(watchdogCommercial())
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('Sécurité');
});

test('les widgets de sécurité ne polluent pas le tableau de bord principal', function (): void {
    $widgets = Filament::getPanel('admin')->getWidgets();

    expect($widgets)->not->toContain(SecurityOverviewWidget::class)
        ->and($widgets)->not->toContain(ThreatLevelWidget::class)
        ->and($widgets)->not->toContain(RecentAlertsWidget::class);
});

test('une connexion réussie est journalisée', function (): void {
    $user = watchdogAdmin();

    $this->get('/admin/login');
    $this->actingAs($user);

    event(new Login('web', $user, false));

    $log = ActivityLog::where('event_type', 'successful_login')->first();

    expect($log)->not->toBeNull()
        ->and($log?->getAttribute('user_id'))->toBe((string) $user->id)
        ->and($log?->getAttribute('risk_level'))->toBe('low');
});

test('une connexion échouée est journalisée', function (): void {
    $this->get('/admin/login');

    event(new Failed('web', null, ['email' => 'inconnu@example.test']));

    $log = ActivityLog::where('event_type', 'failed_login')->first();

    expect($log)->not->toBeNull()
        ->and($log?->getAttribute('risk_level'))->toBe('medium')
        ->and(data_get($log?->getAttribute('event_details'), 'email'))->toBe('inconnu@example.test');
});

test('le scan de sécurité est planifié toutes les quinze minutes', function (): void {
    $scheduled = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'watchdog:scan'));

    expect($scheduled)->not->toBeNull()
        ->and($scheduled->expression)->toBe('*/15 * * * *');
});

test('la quarantaine automatique est désactivée', function (): void {
    expect(config('filament-watchdog.malware_detection.quarantine_enabled'))->toBeFalse()
        ->and(config('filament-watchdog.navigation.security.hidden'))->toBeTrue()
        ->and(config('filament-watchdog.alerts.email_recipients'))->toContain('admin@c2me.fr');
});

test('un utilisateur désactivé n’accède à aucun des deux panneaux', function (): void {
    $inactive = User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => false,
    ]);

    $this->actingAs($inactive)->get('/admin')->assertForbidden();
    $this->actingAs($inactive)->get('/aide')->assertForbidden();
});

test('le listener de connexion est enregistré', function (): void {
    expect(Event::getRawListeners())->toHaveKey(Login::class)
        ->and(Event::getRawListeners())->toHaveKey(Failed::class);
});
