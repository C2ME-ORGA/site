<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Crée le compte d'administration du back-office Filament.
     *
     * Les valeurs sont lues dans la configuration et non dans l'environnement
     * directement : env() ne fonctionne qu'à l'intérieur des fichiers de
     * configuration, et renverrait null une fois la configuration mise en
     * cache en production.
     */
    public function run(): void
    {
        $email = (string) config('site.admin.allowed_emails')[0];
        $password = (string) config('site.admin.password');

        if ($password === '') {
            $this->command?->error('ADMIN_PASSWORD n’est pas défini : compte administrateur non créé.');

            return;
        }

        User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Administrateur C2ME', 'password' => $password],
        );

        $this->command?->info("Compte administrateur prêt : {$email}");
    }
}
