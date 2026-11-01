<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\SecurityAlerts\Pages\ListSecurityAlerts;
use App\Filament\Resources\SecurityAlerts\SecurityAlertResource;
use App\Models\User;
use App\Services\SecurityAlertWorkflow;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use MKWebDesign\FilamentWatchdog\Models\SecurityAlert;

function securityAlertAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function securityAlertCommercial(): User
{
    return User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
}

function securityAlert(string $status = 'new', string $severity = 'high'): SecurityAlert
{
    return SecurityAlert::create([
        'alert_type' => 'file_modified',
        'title' => 'Fichier modifié',
        'description' => 'Un fichier surveillé a été modifié.',
        'severity' => $severity,
        'status' => $status,
        'metadata' => ['file_path' => 'app/Console/Kernel.php'],
    ]);
}

test('un administrateur peut acquitter une alerte', function (): void {
    $admin = securityAlertAdmin();
    $alert = securityAlert();

    app(SecurityAlertWorkflow::class)->acknowledge($alert, $admin);

    $alert->refresh();

    expect($alert->getAttribute('status'))->toBe('acknowledged')
        ->and($alert->getAttribute('acknowledged_by'))->toBe($admin->name)
        ->and($alert->getAttribute('acknowledged_at'))->not->toBeNull();
});

test('un administrateur peut résoudre une alerte nouvelle ou acquittée', function (string $initialStatus): void {
    $admin = securityAlertAdmin();
    $alert = securityAlert($initialStatus);

    app(SecurityAlertWorkflow::class)->resolve($alert, $admin);

    $alert->refresh();

    expect($alert->getAttribute('status'))->toBe('resolved')
        ->and($alert->getAttribute('resolved_by'))->toBe($admin->name)
        ->and($alert->getAttribute('resolved_at'))->not->toBeNull();
})->with(['new', 'acknowledged']);

test('les transitions invalides sont refusées', function (string $status): void {
    $alert = securityAlert($status);

    app(SecurityAlertWorkflow::class)->acknowledge($alert, securityAlertAdmin());
})->with(['acknowledged', 'resolved', 'false_positive'])->throws(DomainException::class);

test('une alerte résolue ne peut pas être résolue deux fois', function (): void {
    $alert = securityAlert('resolved');

    expect(SecurityAlertWorkflow::canResolve($alert))->toBeFalse();

    app(SecurityAlertWorkflow::class)->resolve($alert, securityAlertAdmin());
})->throws(DomainException::class);

test('seul un administrateur peut acquitter ou résoudre une alerte', function (): void {
    $alert = securityAlert();
    $admin = securityAlertAdmin();
    $commercial = securityAlertCommercial();

    expect(Gate::forUser($admin)->allows('acknowledge', $alert))->toBeTrue()
        ->and(Gate::forUser($admin)->allows('resolve', $alert))->toBeTrue()
        ->and(Gate::forUser($commercial)->allows('acknowledge', $alert))->toBeFalse()
        ->and(Gate::forUser($commercial)->allows('resolve', $alert))->toBeFalse();
});

test('aucun champ technique de l’alerte n’est modifiable', function (): void {
    $admin = securityAlertAdmin();
    $alert = securityAlert();

    expect(SecurityAlertResource::canCreate())->toBeFalse()
        ->and(SecurityAlertResource::canEdit($alert))->toBeFalse()
        ->and(SecurityAlertResource::canDelete($alert))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('update', $alert))->toBeFalse()
        ->and(Gate::forUser($admin)->allows('delete', $alert))->toBeFalse();
});

test('un administrateur acquitte une alerte depuis la liste', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = securityAlertAdmin();
    $alert = securityAlert();

    $this->actingAs($admin);

    Livewire::test(ListSecurityAlerts::class)
        ->callTableAction('acknowledge', $alert)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($alert->refresh()->getAttribute('status'))->toBe('acknowledged');
});

test('un administrateur résout une alerte depuis la liste', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $admin = securityAlertAdmin();
    $alert = securityAlert('acknowledged');

    $this->actingAs($admin);

    Livewire::test(ListSecurityAlerts::class)
        ->callTableAction('resolve', $alert)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    expect($alert->refresh()->getAttribute('status'))->toBe('resolved');
});

test('l’action d’acquittement disparaît sur une alerte déjà traitée', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $alert = securityAlert('resolved');

    $this->actingAs(securityAlertAdmin());

    Livewire::test(ListSecurityAlerts::class)
        ->assertTableActionHidden('acknowledge', $alert)
        ->assertTableActionHidden('resolve', $alert);
});

test('un administrateur consulte le détail d’une alerte', function (): void {
    $alert = securityAlert();

    $this->actingAs(securityAlertAdmin())
        ->get('/admin/security-alerts/'.$alert->getKey())
        ->assertOk()
        ->assertSee('Fichier modifié')
        ->assertSee('Acquitter')
        ->assertSee('Résoudre');
});

test('un commercial ne peut ni lister ni traiter les alertes', function (): void {
    $commercial = securityAlertCommercial();
    $alert = securityAlert();

    $this->actingAs($commercial)->get('/admin/security-alerts')->assertForbidden();
    $this->actingAs($commercial)->get('/admin/security-alerts/'.$alert->getKey())->assertForbidden();

    expect(Gate::forUser($commercial)->allows('viewAny', SecurityAlert::class))->toBeFalse();
});

test('un visiteur non connecté n’accède pas aux alertes', function (): void {
    $alert = securityAlert();

    $this->get('/admin/security-alerts')->assertRedirect();
    $this->get('/admin/security-alerts/'.$alert->getKey())->assertRedirect();
});
