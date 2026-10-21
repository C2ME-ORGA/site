<?php

use App\Enums\CrmActivityType;
use App\Enums\UserRole;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\User;

function fieldUser(UserRole $role): User
{
    return User::factory()->create([
        'role' => $role,
        'is_active' => true,
    ]);
}

test('le mode terrain exige une connexion', function (): void {
    $this->get('/terrain')->assertRedirect(route('filament.admin.auth.login'));
});

test('la page du mode terrain s’ouvre pour un utilisateur connecté', function (): void {
    $this->actingAs(fieldUser(UserRole::Commercial))
        ->get('/terrain')
        ->assertOk()
        ->assertSee('Mode terrain');
});

test('le snapshot est limité aux comptes du commercial', function (): void {
    $admin = fieldUser(UserRole::Admin);
    $commercial = fieldUser(UserRole::Commercial);

    $mine = CrmAccount::factory()->create(['owner_id' => $commercial->id]);
    $other = CrmAccount::factory()->create(['owner_id' => $admin->id]);

    $this->actingAs($commercial)
        ->getJson('/terrain/snapshot')
        ->assertOk()
        ->assertJsonCount(1, 'accounts')
        ->assertJsonPath('accounts.0.id', $mine->id);

    $this->actingAs($admin)
        ->getJson('/terrain/snapshot')
        ->assertOk()
        ->assertJsonCount(2, 'accounts');
});

test('le snapshot expose les contacts et activités du compte', function (): void {
    $commercial = fieldUser(UserRole::Commercial);
    $account = CrmAccount::factory()->create(['owner_id' => $commercial->id]);

    CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'crm_contact_id' => null,
        'user_id' => $commercial->id,
        'type' => CrmActivityType::Call,
        'subject' => 'Appel de suivi',
    ]);

    $this->actingAs($commercial)
        ->getJson('/terrain/snapshot')
        ->assertOk()
        ->assertJsonPath('accounts.0.activities.0.subject', 'Appel de suivi');
});

test('la synchronisation enregistre le compte rendu et la clôture', function (): void {
    $commercial = fieldUser(UserRole::Commercial);
    $account = CrmAccount::factory()->create(['owner_id' => $commercial->id]);

    $activity = CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'crm_contact_id' => null,
        'user_id' => $commercial->id,
        'type' => CrmActivityType::Call,
        'body' => 'Ancien compte rendu',
        'completed_at' => null,
    ]);

    $this->actingAs($commercial)
        ->postJson('/terrain/sync', [
            'activities' => [[
                'id' => $activity->id,
                'body' => 'Compte rendu terrain',
                'completed_at' => now()->toIso8601String(),
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('activities.0.body', 'Compte rendu terrain');

    $activity->refresh();

    expect($activity->body)->toBe('Compte rendu terrain')
        ->and($activity->completed_at)->not->toBeNull();
});

test('la synchronisation ignore les activités d’un autre commercial', function (): void {
    $owner = fieldUser(UserRole::Admin);
    $commercial = fieldUser(UserRole::Commercial);

    $account = CrmAccount::factory()->create(['owner_id' => $owner->id]);

    $activity = CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'crm_contact_id' => null,
        'user_id' => $owner->id,
        'type' => CrmActivityType::Call,
        'body' => 'À ne pas modifier',
    ]);

    $this->actingAs($commercial)
        ->postJson('/terrain/sync', [
            'activities' => [[
                'id' => $activity->id,
                'body' => 'Modification interdite',
            ]],
        ])
        ->assertOk()
        ->assertJsonCount(0, 'activities');

    expect($activity->fresh()->body)->toBe('À ne pas modifier');
});

test('la synchronisation valide la charge utile', function (): void {
    $commercial = fieldUser(UserRole::Commercial);

    $this->actingAs($commercial)
        ->postJson('/terrain/sync', [
            'activities' => [['id' => 'invalide']],
        ])
        ->assertUnprocessable();
});
