<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Artisan;

test('le seeder crée l’administrateur avec le mot de passe de la configuration', function (): void {
    config(['site.admin.password' => 'Mot-De-Passe-De-Test-2026']);

    Artisan::call('db:seed', ['--class' => AdminUserSeeder::class]);

    $user = User::where('email', config('site.admin.allowed_emails')[0])->firstOrFail();

    expect(password_verify('Mot-De-Passe-De-Test-2026', $user->password))->toBeTrue();
});

test('le seeder refuse de créer un compte sans mot de passe défini', function (): void {
    config(['site.admin.password' => '']);

    Artisan::call('db:seed', ['--class' => AdminUserSeeder::class]);

    $this->assertDatabaseMissing('users', [
        'email' => config('site.admin.allowed_emails')[0],
    ]);
});
