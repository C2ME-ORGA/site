<?php

namespace App\Policies;

use App\Models\User;
use MKWebDesign\FilamentWatchdog\Models\SecurityAlert;

/**
 * Les alertes de sécurité sont en lecture seule et réservées aux
 * administrateurs : aucun rôle ne doit pouvoir les créer ou les modifier.
 */
class SecurityAlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SecurityAlert $securityAlert): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, SecurityAlert $securityAlert): bool
    {
        return false;
    }

    public function delete(User $user, SecurityAlert $securityAlert): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
