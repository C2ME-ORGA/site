<?php

use App\Enums\CrmActivityType;
use App\Enums\UserRole;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\User;
use Illuminate\Support\Str;

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

test('le snapshot expose les contacts et les activités séparément', function (): void {
    $commercial = fieldUser(UserRole::Commercial);
    $account = CrmAccount::factory()->create(['owner_id' => $commercial->id]);

    $contact = CrmContact::factory()->create([
        'crm_account_id' => $account->id,
        'first_name' => 'Alice',
        'last_name' => 'Martin',
    ]);

    CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'crm_contact_id' => $contact->id,
        'user_id' => $commercial->id,
        'type' => CrmActivityType::Call,
        'subject' => 'Appel de suivi',
    ]);

    $this->actingAs($commercial)
        ->getJson('/terrain/snapshot')
        ->assertOk()
        ->assertJsonPath('accounts.0.contacts.0.first_name', 'Alice')
        ->assertJsonMissingPath('accounts.0.activities');

    $this->actingAs($commercial)
        ->getJson('/terrain/activities')
        ->assertOk()
        ->assertJsonPath('activities.0.subject', 'Appel de suivi')
        ->assertJsonPath('activities.0.crm_contact_id', $contact->id);
});

test('les activités sont paginées sans limite par compte', function (): void {
    $commercial = fieldUser(UserRole::Commercial);
    $account = CrmAccount::factory()->create(['owner_id' => $commercial->id]);

    CrmActivity::factory()->count(3)->create([
        'crm_account_id' => $account->id,
        'user_id' => $commercial->id,
        'type' => CrmActivityType::Call,
    ]);

    $first = $this->actingAs($commercial)
        ->getJson('/terrain/activities?limit=2')
        ->assertOk()
        ->assertJsonCount(2, 'activities');

    $cursor = $first->json('next_cursor');
    expect($cursor)->not->toBeNull();

    $this->actingAs($commercial)
        ->getJson('/terrain/activities?limit=2&after_id='.$cursor)
        ->assertOk()
        ->assertJsonCount(1, 'activities')
        ->assertJsonPath('next_cursor', null);
});

test('les activités des comptes d’un autre commercial sont exclues', function (): void {
    $owner = fieldUser(UserRole::Admin);
    $commercial = fieldUser(UserRole::Commercial);
    $account = CrmAccount::factory()->create(['owner_id' => $owner->id]);

    CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'user_id' => $owner->id,
        'type' => CrmActivityType::Call,
    ]);

    $this->actingAs($commercial)
        ->getJson('/terrain/activities')
        ->assertOk()
        ->assertJsonCount(0, 'activities');
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

test('une activité peut être créée hors ligne de façon idempotente', function (): void {
    $commercial = fieldUser(UserRole::Commercial);
    $account = CrmAccount::factory()->create(['owner_id' => $commercial->id]);
    $clientUuid = (string) Str::uuid();

    $payload = [
        'operations' => [[
            'op_id' => 'op-create-1',
            'type' => 'create_activity',
            'client_uuid' => $clientUuid,
            'payload' => [
                'crm_account_id' => $account->id,
                'type' => CrmActivityType::Visit->value,
                'subject' => 'Visite de chantier',
                'body' => 'Relevé effectué.',
                'due_at' => now()->addDay()->toIso8601String(),
            ],
        ]],
    ];

    $first = $this->actingAs($commercial)
        ->postJson('/terrain/sync', $payload)
        ->assertOk()
        ->assertJsonPath('results.0.status', 'ok')
        ->assertJsonPath('results.0.server.subject', 'Visite de chantier');

    $activityId = (int) $first->json('results.0.entity_id');

    $this->actingAs($commercial)
        ->postJson('/terrain/sync', $payload)
        ->assertOk()
        ->assertJsonPath('results.0.entity_id', $activityId);

    expect(CrmActivity::query()->where('client_uuid', $clientUuid)->count())->toBe(1)
        ->and(CrmActivity::query()->find($activityId)?->user_id)->toBe($commercial->id);
});

test('un commercial ne peut pas créer une activité sur le compte d’un autre commercial', function (): void {
    $owner = fieldUser(UserRole::Admin);
    $commercial = fieldUser(UserRole::Commercial);
    $account = CrmAccount::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($commercial)
        ->postJson('/terrain/sync', [
            'operations' => [[
                'op_id' => 'op-create-denied',
                'type' => 'create_activity',
                'client_uuid' => (string) Str::uuid(),
                'payload' => [
                    'crm_account_id' => $account->id,
                    'type' => CrmActivityType::Visit->value,
                    'subject' => 'Visite interdite',
                ],
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'error');

    expect(CrmActivity::query()->count())->toBe(0);
});

test('les coordonnées d’un compte et d’un contact sont modifiables', function (): void {
    $commercial = fieldUser(UserRole::Commercial);
    $account = CrmAccount::factory()->create(['owner_id' => $commercial->id]);
    $contact = CrmContact::factory()->create(['crm_account_id' => $account->id]);

    $this->actingAs($commercial)
        ->postJson('/terrain/sync', [
            'operations' => [
                [
                    'op_id' => 'op-account-1',
                    'type' => 'update_account',
                    'entity_id' => $account->id,
                    'payload' => [
                        'phone' => '02 40 00 00 00',
                        'city' => 'Nantes',
                    ],
                ],
                [
                    'op_id' => 'op-contact-1',
                    'type' => 'update_contact',
                    'entity_id' => $contact->id,
                    'payload' => [
                        'phone' => '06 00 00 00 00',
                        'job_title' => 'Responsable travaux',
                    ],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'ok')
        ->assertJsonPath('results.1.status', 'ok');

    expect($account->fresh()->phone)->toBe('02 40 00 00 00')
        ->and($account->fresh()->city)->toBe('Nantes')
        ->and($contact->fresh()->phone)->toBe('06 00 00 00 00')
        ->and($contact->fresh()->job_title)->toBe('Responsable travaux');
});

test('un commercial ne peut pas modifier le compte ou le contact d’un autre commercial', function (): void {
    $owner = fieldUser(UserRole::Admin);
    $commercial = fieldUser(UserRole::Commercial);
    $account = CrmAccount::factory()->create(['owner_id' => $owner->id, 'phone' => 'Inchangé']);
    $contact = CrmContact::factory()->create([
        'crm_account_id' => $account->id,
        'phone' => 'Inchangé',
    ]);

    $this->actingAs($commercial)
        ->postJson('/terrain/sync', [
            'operations' => [
                [
                    'op_id' => 'op-account-denied',
                    'type' => 'update_account',
                    'entity_id' => $account->id,
                    'payload' => ['phone' => 'Modifié'],
                ],
                [
                    'op_id' => 'op-contact-denied',
                    'type' => 'update_contact',
                    'entity_id' => $contact->id,
                    'payload' => ['phone' => 'Modifié'],
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'error')
        ->assertJsonPath('results.1.status', 'error');

    expect($account->fresh()->phone)->toBe('Inchangé')
        ->and($contact->fresh()->phone)->toBe('Inchangé');
});

test('le snapshot incrémental ne renvoie que les changements et les suppressions', function (): void {
    $commercial = fieldUser(UserRole::Commercial);

    $changed = CrmAccount::factory()->create(['owner_id' => $commercial->id, 'name' => 'Compte modifié']);
    $deleted = CrmAccount::factory()->create(['owner_id' => $commercial->id, 'name' => 'Compte supprimé']);
    $contact = CrmContact::factory()->create([
        'crm_account_id' => $changed->id,
        'last_name' => 'À supprimer',
    ]);

    $full = $this->actingAs($commercial)
        ->getJson('/terrain/snapshot')
        ->assertOk()
        ->assertJsonPath('full', true)
        ->assertJsonCount(2, 'accounts');

    $cursor = $full->json('cursor');
    expect($cursor)->toBeString();

    $this->travel(2)->seconds();

    $changed->update(['phone' => '02 00 00 00 00']);
    $contact->delete();
    $deleted->delete();

    $this->actingAs($commercial)
        ->getJson('/terrain/snapshot?updated_since='.urlencode($cursor))
        ->assertOk()
        ->assertJsonPath('full', false)
        ->assertJsonCount(1, 'accounts')
        ->assertJsonPath('accounts.0.id', $changed->id)
        ->assertJsonPath('accounts.0.phone', '02 00 00 00 00')
        ->assertJsonPath('deleted.accounts.0', $deleted->id)
        ->assertJsonPath('deleted.contacts.0', $contact->id);
});

test('les activités peuvent être synchronisées de façon incrémentale', function (): void {
    $commercial = fieldUser(UserRole::Commercial);
    $account = CrmAccount::factory()->create(['owner_id' => $commercial->id]);

    CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'user_id' => $commercial->id,
        'type' => CrmActivityType::Call,
        'subject' => 'Ancienne activité',
    ]);

    $this->travel(2)->seconds();

    $cursor = now()->toIso8601String();

    $this->travel(2)->seconds();

    CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'user_id' => $commercial->id,
        'type' => CrmActivityType::Visit,
        'subject' => 'Nouvelle activité',
    ]);

    $this->actingAs($commercial)
        ->getJson('/terrain/activities?updated_since='.urlencode($cursor))
        ->assertOk()
        ->assertJsonCount(1, 'activities')
        ->assertJsonPath('activities.0.subject', 'Nouvelle activité');
});

test('une modification concurrente d’un compte retourne un conflit puis peut être résolue', function (): void {
    $commercial = fieldUser(UserRole::Commercial);
    $account = CrmAccount::factory()->create([
        'owner_id' => $commercial->id,
        'phone' => 'Version initiale',
    ]);
    $baseUpdatedAt = $account->updated_at?->toIso8601String();

    $this->travel(2)->seconds();

    $account->update(['phone' => 'Version serveur']);

    $conflict = $this->actingAs($commercial)
        ->postJson('/terrain/sync', [
            'operations' => [[
                'op_id' => 'op-conflict-account',
                'type' => 'update_account',
                'entity_id' => $account->id,
                'payload' => ['phone' => 'Version locale'],
                'base_updated_at' => $baseUpdatedAt,
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'conflict')
        ->assertJsonPath('results.0.server.phone', 'Version serveur');

    expect($account->fresh()->phone)->toBe('Version serveur');

    $this->actingAs($commercial)
        ->postJson('/terrain/sync', [
            'operations' => [[
                'op_id' => 'op-conflict-account-retry',
                'type' => 'update_account',
                'entity_id' => $account->id,
                'payload' => ['phone' => 'Version locale'],
                'base_updated_at' => $conflict->json('results.0.updated_at'),
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'ok');

    expect($account->fresh()->phone)->toBe('Version locale');
});

test('les conflits d’activité et de contact sont détectés', function (): void {
    $commercial = fieldUser(UserRole::Commercial);
    $account = CrmAccount::factory()->create(['owner_id' => $commercial->id]);
    $contact = CrmContact::factory()->create([
        'crm_account_id' => $account->id,
        'phone' => 'Version initiale',
    ]);
    $activity = CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'user_id' => $commercial->id,
        'type' => CrmActivityType::Visit,
        'body' => 'Version initiale',
    ]);

    $activityBase = $activity->updated_at?->toIso8601String();
    $contactBase = $contact->updated_at?->toIso8601String();

    $this->travel(2)->seconds();

    $activity->update(['body' => 'Version serveur']);
    $contact->update(['phone' => 'Version serveur']);

    $this->actingAs($commercial)
        ->postJson('/terrain/sync', [
            'operations' => [
                [
                    'op_id' => 'op-conflict-activity',
                    'type' => 'update_activity',
                    'entity_id' => $activity->id,
                    'payload' => ['body' => 'Version locale'],
                    'base_updated_at' => $activityBase,
                ],
                [
                    'op_id' => 'op-conflict-contact',
                    'type' => 'update_contact',
                    'entity_id' => $contact->id,
                    'payload' => ['phone' => 'Version locale'],
                    'base_updated_at' => $contactBase,
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.status', 'conflict')
        ->assertJsonPath('results.0.server.body', 'Version serveur')
        ->assertJsonPath('results.1.status', 'conflict')
        ->assertJsonPath('results.1.server.phone', 'Version serveur');

    expect($activity->fresh()->body)->toBe('Version serveur')
        ->and($contact->fresh()->phone)->toBe('Version serveur');
});
