<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Le compte d'administration est le seul utilisateur du site : db:seed
     * crée tout ce qu'il faut au déploiement, sans classe à préciser.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
    }
}
