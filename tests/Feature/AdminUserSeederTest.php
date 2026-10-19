<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Artisan;

test('le seeder crée l’administrateur avec le mot de passe de la configuration', function (): void {
    config(['site.admin.password' => 'Mot-De-Passe-De-Test-2026']);

    Artisan::call('db:seed', ['--class' => AdminUserSeeder::class]);

    $user = User::where('email', config('site.admin.allowed_emails')[0])->firstOrFail();

    expect(password_verify('Mot-De-Passe-De-Test-2026', $user->password))->toBeTrue()
        ->and($user->role)->toBe(UserRole::Admin)
        ->and($user->is_active)->toBeTrue();
});

test('le seeder promeut un compte existant en administrateur', function (): void {
    config(['site.admin.password' => 'Mot-De-Passe-De-Test-2026']);

    User::factory()->create([
        'email' => config('site.admin.allowed_emails')[0],
        'role' => UserRole::Commercial,
    ]);

    Artisan::call('db:seed', ['--class' => AdminUserSeeder::class]);

    $user = User::where('email', config('site.admin.allowed_emails')[0])->firstOrFail();

    expect($user->role)->toBe(UserRole::Admin);
});

test('le seeder refuse de créer un compte sans mot de passe défini', function (): void {
    config(['site.admin.password' => '']);

    Artisan::call('db:seed', ['--class' => AdminUserSeeder::class]);

    $this->assertDatabaseMissing('users', [
        'email' => config('site.admin.allowed_emails')[0],
    ]);
});
