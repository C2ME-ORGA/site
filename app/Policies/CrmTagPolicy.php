<?php

namespace App\Policies;

use App\Models\CrmTag;
use App\Models\User;

/**
 * Les étiquettes forment un vocabulaire partagé par toute l'équipe : la
 * création, le renommage et la fusion sont ouverts à tous les utilisateurs
 * actifs, la suppression est réservée aux administrateurs car elle retire
 * silencieusement l'étiquetage de chaque compte concerné.
 */
class CrmTagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, CrmTag $tag): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, CrmTag $tag): bool
    {
        return $user->is_active;
    }

    public function delete(User $user, CrmTag $tag): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function merge(User $user): bool
    {
        return $user->is_active;
    }
}
