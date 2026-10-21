<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\CrmActivityType;
use App\Enums\CrmBook;
use App\Enums\UserRole;
use App\Filament\Pages\FollowUps;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function followUpAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function followUpCommercial(): User
{
    return User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
}

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00:00'));
});

test('la page relances est accessible et liste les comptes dus', function (): void {
    $admin = followUpAdmin();

    $due = CrmAccount::factory()->create([
        'book' => CrmBook::Prescribers,
        'owner_id' => $admin->id,
        'name' => 'Compte À Relancer',
        'next_follow_up_at' => now()->subDay()->setTime(14, 0),
    ]);

    $future = CrmAccount::factory()->create([
        'book' => CrmBook::Prescribers,
        'owner_id' => $admin->id,
        'name' => 'Compte Futur',
        'next_follow_up_at' => now()->addDays(3),
    ]);

    $inactive = CrmAccount::factory()->create([
        'book' => CrmBook::Prescribers,
        'owner_id' => $admin->id,
        'status' => AccountStatus::Inactive,
        'next_follow_up_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/relances')
        ->assertOk()
        ->assertSee('Relances à traiter')
        ->assertSee('Compte À Relancer');

    Livewire::actingAs($admin)
        ->test(FollowUps::class)
        ->assertCanSeeTableRecords([$due])
        ->assertCanNotSeeTableRecords([$future, $inactive]);
});

test('un administrateur peut afficher les comptes inactifs', function (): void {
    $admin = followUpAdmin();

    $inactive = CrmAccount::factory()->create([
        'owner_id' => $admin->id,
        'status' => AccountStatus::Inactive,
        'next_follow_up_at' => now()->subDay(),
    ]);

    Livewire::actingAs($admin)
        ->test(FollowUps::class)
        ->assertCanNotSeeTableRecords([$inactive])
        ->filterTable('account_state', 'all')
        ->assertCanSeeTableRecords([$inactive]);
});

test('un commercial ne voit pas les comptes inactifs', function (): void {
    $commercial = followUpCommercial();

    $inactive = CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'status' => AccountStatus::Inactive,
        'next_follow_up_at' => now()->subDay(),
    ]);

    Livewire::actingAs($commercial)
        ->test(FollowUps::class)
        ->assertCanNotSeeTableRecords([$inactive]);
});

test('un commercial ne voit que ses propres relances', function (): void {
    $commercial = followUpCommercial();
    $other = followUpCommercial();

    $own = CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->subDay(),
    ]);

    $otherAccount = CrmAccount::factory()->create([
        'owner_id' => $other->id,
        'next_follow_up_at' => now()->subDay(),
    ]);

    Livewire::actingAs($commercial)
        ->test(FollowUps::class)
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$otherAccount]);
});

test('marquer une relance comme faite efface l’échéance et crée une activité', function (): void {
    $admin = followUpAdmin();

    $account = CrmAccount::factory()->create([
        'owner_id' => $admin->id,
        'next_follow_up_at' => now()->subDay()->setTime(14, 0),
    ]);

    Livewire::actingAs($admin)
        ->test(FollowUps::class)
        ->callTableAction('markFollowedUp', $account, [
            'report' => 'Client rappelé, devis à suivre.',
        ])
        ->assertHasNoActionErrors();

    $account->refresh();
    $activity = CrmActivity::query()
        ->where('crm_account_id', $account->id)
        ->sole();

    expect($account->next_follow_up_at)->toBeNull()
        ->and($account->last_contacted_at)->not->toBeNull()
        ->and($activity->type)->toBe(CrmActivityType::FollowUp)
        ->and($activity->body)->toBe('Client rappelé, devis à suivre.')
        ->and($activity->completed_at)->not->toBeNull();
});

test('reporter une relance met à jour la prochaine échéance', function (): void {
    $admin = followUpAdmin();

    $account = CrmAccount::factory()->create([
        'owner_id' => $admin->id,
        'next_follow_up_at' => now()->subDay()->setTime(14, 0),
    ]);

    Livewire::actingAs($admin)
        ->test(FollowUps::class)
        ->callTableAction('postpone', $account, [
            'preset' => '7',
        ])
        ->assertHasNoActionErrors();

    $account->refresh();

    expect($account->next_follow_up_at?->format('Y-m-d H:i'))->toBe(
        now()->addDays(7)->setTime(9, 0)->format('Y-m-d H:i'),
    );
});

test('reporter à une date personnalisée met à jour l’échéance', function (): void {
    $admin = followUpAdmin();

    $account = CrmAccount::factory()->create([
        'owner_id' => $admin->id,
        'next_follow_up_at' => now()->subDay()->setTime(14, 0),
    ]);

    Livewire::actingAs($admin)
        ->test(FollowUps::class)
        ->callTableAction('postpone', $account, [
            'preset' => 'custom',
            'next_follow_up_at' => '2026-11-05 11:30:00',
        ])
        ->assertHasNoActionErrors();

    $account->refresh();

    expect($account->next_follow_up_at?->format('Y-m-d H:i'))->toBe('2026-11-05 11:30');
});

test('le badge de navigation reflète les relances dues de l’utilisateur', function (): void {
    $admin = followUpAdmin();
    $commercial = followUpCommercial();

    CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->subDay(),
    ]);

    CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'next_follow_up_at' => now()->addDays(2),
    ]);

    $this->actingAs($admin);
    expect(FollowUps::getNavigationBadge())->toBe('1');

    $this->actingAs($commercial);
    expect(FollowUps::getNavigationBadge())->toBe('1');
});

test('la stat relances du dashboard pointe vers la page relances', function (): void {
    $admin = followUpAdmin();

    CrmAccount::factory()->create([
        'owner_id' => $admin->id,
        'next_follow_up_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('/admin/relances', false);
});
