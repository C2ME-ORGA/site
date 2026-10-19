<?php

namespace App\Policies;

use App\Models\CrmAccount;
use App\Models\User;

class CrmAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, CrmAccount $account): bool
    {
        return $user->isAdmin() || $account->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, CrmAccount $account): bool
    {
        return $user->isAdmin() || $account->owner_id === $user->id;
    }

    public function delete(User $user, CrmAccount $account): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, CrmAccount $account): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, CrmAccount $account): bool
    {
        return $user->isAdmin();
    }
}
