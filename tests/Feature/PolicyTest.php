<?php

use App\Enums\CrmBook;
use App\Enums\UserRole;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function policyAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
}

function policyCommercial(): User
{
    return User::factory()->create(['role' => UserRole::Commercial, 'is_active' => true]);
}

function ownedAccount(User $owner): CrmAccount
{
    return CrmAccount::factory()->create([
        'book' => CrmBook::Prescribers,
        'owner_id' => $owner->id,
    ]);
}

test('un commercial ne modifie que les comptes dont il est propriétaire', function (): void {
    $owner = policyCommercial();
    $other = policyCommercial();

    $account = ownedAccount($owner);

    expect($owner->can('view', $account))->toBeTrue()
        ->and($owner->can('update', $account))->toBeTrue()
        ->and($owner->can('delete', $account))->toBeFalse()
        ->and($other->can('view', $account))->toBeFalse()
        ->and($other->can('update', $account))->toBeFalse();
});

test('un administrateur gère tous les comptes', function (): void {
    $admin = policyAdmin();
    $account = ownedAccount(policyCommercial());

    expect($admin->can('view', $account))->toBeTrue()
        ->and($admin->can('update', $account))->toBeTrue()
        ->and($admin->can('delete', $account))->toBeTrue();
});

test('un utilisateur désactivé n’accède pas aux listes', function (): void {
    $user = User::factory()->create(['is_active' => false]);

    expect($user->can('viewAny', CrmAccount::class))->toBeFalse()
        ->and($user->can('viewAny', Quote::class))->toBeFalse()
        ->and($user->can('viewAny', CrmActivity::class))->toBeFalse();
});

test('un commercial ne modifie que ses devis', function (): void {
    $owner = policyCommercial();
    $other = policyCommercial();

    $quote = Quote::factory()->create([
        'crm_account_id' => ownedAccount($owner)->id,
        'owner_id' => $owner->id,
    ]);

    expect($owner->can('update', $quote))->toBeTrue()
        ->and($owner->can('delete', $quote))->toBeFalse()
        ->and($other->can('view', $quote))->toBeFalse();

    expect(policyAdmin()->can('delete', $quote))->toBeTrue();
});

test('les activités sont visibles par leur auteur ou le propriétaire du compte', function (): void {
    $owner = policyCommercial();
    $author = policyCommercial();
    $other = policyCommercial();

    $account = ownedAccount($owner);

    $activity = CrmActivity::factory()->create([
        'crm_account_id' => $account->id,
        'user_id' => $author->id,
    ]);

    expect($author->can('update', $activity))->toBeTrue()
        ->and($owner->can('view', $activity))->toBeTrue()
        ->and($owner->can('delete', $activity))->toBeFalse()
        ->and($other->can('view', $activity))->toBeFalse();
});

test('un commercial accède aux messages partagés mais pas aux boîtes privées des autres', function (): void {
    $commercial = policyCommercial();
    $other = policyCommercial();

    $shared = EmailMailbox::factory()->create([
        'email' => 'commercial@c2me.fr',
        'is_shared' => true,
    ]);

    $private = EmailMailbox::factory()->forUser($other)->create([
        'email' => 'autre@c2me.fr',
        'is_shared' => false,
    ]);

    $sharedMessage = EmailMessage::factory()->create(['email_mailbox_id' => $shared->id]);
    $privateMessage = EmailMessage::factory()->create(['email_mailbox_id' => $private->id]);

    expect($commercial->can('view', $sharedMessage))->toBeTrue()
        ->and($commercial->can('view', $privateMessage))->toBeFalse()
        ->and($commercial->can('delete', $sharedMessage))->toBeFalse();

    expect(policyAdmin()->can('view', $privateMessage))->toBeTrue()
        ->and(policyAdmin()->can('delete', $privateMessage))->toBeTrue();
});
