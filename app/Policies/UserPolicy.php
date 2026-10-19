<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin() || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->isAdmin() || $user->id === $model->id) {
            return false;
        }

        // Ne jamais supprimer le dernier administrateur actif, sous peine de
        // se verrouiller hors du back-office.
        if ($model->isAdmin() && self::activeAdminCount() <= 1) {
            return false;
        }

        return true;
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    private static function activeAdminCount(): int
    {
        return User::query()
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->count();
    }
}
