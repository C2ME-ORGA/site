<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Les réalisations alimentent le site public : leur gestion est réservée aux
 * administrateurs, comme les autres contenus éditoriaux.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function reorder(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }
}
