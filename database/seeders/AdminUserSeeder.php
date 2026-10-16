<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Crée le compte d'administration du back-office Filament.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@c2me.fr');
        $password = env('ADMIN_PASSWORD', 'C2me-Admin-2026!');

        User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Administrateur C2ME', 'password' => $password],
        );

        $this->command?->info("Compte administrateur prêt : {$email}");
    }
}
