<?php

namespace App\Policies;

use App\Models\User;
use MKWebDesign\FilamentWatchdog\Models\ActivityLog;

/**
 * Le journal d'activité de sécurité est en lecture seule et réservé aux
 * administrateurs.
 */
class ActivityLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ActivityLog $activityLog): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function delete(User $user, ActivityLog $activityLog): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
