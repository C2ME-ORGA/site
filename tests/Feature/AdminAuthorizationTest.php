<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

function roleAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

function roleCommercial(): User
{
    return User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);
}

test('un commercial accède aux carnets et aux demandes', function (string $url): void {
    $this->actingAs(roleCommercial())
        ->get($url)
        ->assertOk();
})->with([
    '/admin/prescriber-accounts',
    '/admin/industrial-accounts',
    '/admin/building-company-accounts',
    '/admin/contact-requests',
    '/admin/quote-requests',
]);

test('un commercial n’accède pas à la gestion des utilisateurs ni des boîtes', function (string $url): void {
    $this->actingAs(roleCommercial())
        ->get($url)
        ->assertForbidden();
})->with([
    '/admin/users',
    '/admin/mailboxes',
]);

test('un administrateur accède à la gestion des utilisateurs et des boîtes', function (string $url): void {
    $this->actingAs(roleAdmin())
        ->get($url)
        ->assertOk();
})->with([
    '/admin/users',
    '/admin/users/create',
    '/admin/mailboxes',
]);

test('la commande crm:make-admin promeut un compte existant', function (): void {
    $user = roleCommercial();

    $this->artisan('crm:make-admin', ['email' => $user->email])
        ->assertSuccessful();

    expect($user->fresh()->role)->toBe(UserRole::Admin);
});

test('la commande crm:make-admin échoue pour une adresse inconnue', function (): void {
    $this->artisan('crm:make-admin', ['email' => 'inconnu@c2me.fr'])
        ->assertFailed();
});

test('un administrateur ne peut pas supprimer son propre compte', function (): void {
    $admin = roleAdmin();

    expect(Gate::forUser($admin)->allows('delete', $admin))->toBeFalse();
});

test('le dernier administrateur actif ne peut pas être supprimé par un autre', function (): void {
    $admin = roleAdmin();
    $other = roleAdmin();

    // Deux administrateurs : la suppression est autorisée.
    expect(Gate::forUser($admin)->allows('delete', $other))->toBeTrue();

    $other->forceFill(['is_active' => false])->save();

    // Plus qu'un seul actif : la suppression est bloquée.
    expect(Gate::forUser($admin)->allows('delete', $other))->toBeFalse();
});
