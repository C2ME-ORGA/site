<?php

namespace App\Policies;

use App\Models\User;
use MKWebDesign\FilamentWatchdog\Models\FileIntegrityCheck;

/**
 * Le contrôle d'intégrité est en lecture seule et réservé aux administrateurs.
 */
class FileIntegrityCheckPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, FileIntegrityCheck $fileIntegrityCheck): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, FileIntegrityCheck $fileIntegrityCheck): bool
    {
        return false;
    }

    public function delete(User $user, FileIntegrityCheck $fileIntegrityCheck): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
