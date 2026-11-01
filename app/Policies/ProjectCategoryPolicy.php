<?php

namespace App\Policies;

use App\Models\ProjectCategory;
use App\Models\User;

class ProjectCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function view(User $user, ProjectCategory $category): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function update(User $user, ProjectCategory $category): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function delete(User $user, ProjectCategory $category): bool
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
