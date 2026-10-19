<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:make-admin {email}')]
#[Description('Attribue le rôle administrateur à un compte existant (secours).')]
class MakeUserAdmin extends Command
{
    /**
     * Permet de débloquer le back-office depuis le serveur, sans dépendre de
     * l'interface — utile si le dernier administrateur a perdu son rôle.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        if (! is_string($email) || $email === '') {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("Aucun utilisateur avec l’adresse : {$email}");

            return self::FAILURE;
        }

        $user->forceFill([
            'role' => UserRole::Admin,
            'is_active' => true,
        ])->save();

        $this->info("{$user->name} ({$email}) est désormais administrateur.");

        return self::SUCCESS;
    }
}
