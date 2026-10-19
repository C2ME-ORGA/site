<?php

namespace App\Policies;

use App\Models\CrmOpportunity;
use App\Models\User;

class CrmOpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, CrmOpportunity $opportunity): bool
    {
        return $user->isAdmin() || $opportunity->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, CrmOpportunity $opportunity): bool
    {
        return $user->isAdmin() || $opportunity->owner_id === $user->id;
    }

    public function delete(User $user, CrmOpportunity $opportunity): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, CrmOpportunity $opportunity): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, CrmOpportunity $opportunity): bool
    {
        return $user->isAdmin();
    }
}
