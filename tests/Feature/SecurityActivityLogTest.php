<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use MKWebDesign\FilamentWatchdog\Models\ActivityLog;
use MKWebDesign\FilamentWatchdog\Models\SecurityAlert;

function activityLogUser(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function failedLoginLog(string $ip = '127.0.0.1', int $minutesAgo = 5): ActivityLog
{
    $log = new ActivityLog([
        'event_type' => 'failed_login',
        'user_id' => null,
        'ip_address' => $ip,
        'user_agent' => 'test',
        'event_details' => ['email' => 'test@example.test'],
        'risk_level' => 'medium',
    ]);

    $log->setAttribute('created_at', now()->subMinutes($minutesAgo));
    $log->save();

    return $log;
}

test('un événement hors requête web ne journalise rien et ne lève pas d’erreur', function (): void {
    $user = activityLogUser();

    event(new Login('web', $user, false));
    event(new Failed('web', null, ['email' => 'inconnu@example.test']));

    expect(ActivityLog::count())->toBe(0);
});

test('un échec sans adresse e-mail est journalisé sans erreur', function (): void {
    $this->get('/admin/login');

    event(new Failed('web', null, []));

    $log = ActivityLog::query()->where('event_type', 'failed_login')->first();

    expect($log)->not->toBeNull()
        ->and(data_get($log?->getAttribute('event_details'), 'email'))->toBe('');
});

test('le mot de passe n’est jamais journalisé', function (): void {
    $this->get('/admin/login');

    event(new Failed('web', null, [
        'email' => 'a@b.test',
        'password' => 'secret-value-1234',
    ]));

    $log = ActivityLog::query()->first();
    $content = (string) json_encode($log?->getAttribute('event_details'));

    expect($content)->not->toContain('secret-value-1234')
        ->and($content)->not->toContain('password');
});

test('cinq échecs depuis la même IP déclenchent une alerte de force brute', function (): void {
    $this->get('/admin/login');

    foreach (range(1, 5) as $index) {
        failedLoginLog(minutesAgo: $index);
    }

    event(new Failed('web', null, ['email' => 'brute@example.test']));

    expect(SecurityAlert::query()->where('alert_type', 'brute_force_attempt')->count())->toBe(1)
        ->and(ActivityLog::query()->where('event_type', 'failed_login')->count())->toBe(6);
});

test('les échecs d’une autre IP ne déclenchent pas d’alerte', function (): void {
    $this->get('/admin/login');

    foreach (range(1, 5) as $index) {
        failedLoginLog(ip: '10.0.0.'.$index, minutesAgo: $index);
    }

    event(new Failed('web', null, ['email' => 'brute@example.test']));

    expect(SecurityAlert::query()->where('alert_type', 'brute_force_attempt')->count())->toBe(0);
});

test('la purge supprime les journaux au-delà de la rétention et conserve les alertes non résolues', function (): void {
    $old = failedLoginLog(minutesAgo: 60 * 24 * 40);

    SecurityAlert::create([
        'alert_type' => 'test',
        'title' => 'Ancienne alerte non résolue',
        'description' => 'Test',
        'severity' => 'low',
        'status' => 'new',
    ])->forceFill(['created_at' => now()->subDays(40)])->save();

    artisanCommand($this, 'watchdog:cleanup', ['--force' => true])->assertExitCode(0);

    expect(ActivityLog::query()->whereKey($old->getKey())->exists())->toBeFalse()
        ->and(SecurityAlert::count())->toBe(1);
});
