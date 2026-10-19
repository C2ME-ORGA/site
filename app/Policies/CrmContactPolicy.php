<?php

namespace App\Policies;

use App\Models\CrmContact;
use App\Models\User;

class CrmContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, CrmContact $contact): bool
    {
        return $user->isAdmin() || $contact->account?->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, CrmContact $contact): bool
    {
        return $user->isAdmin() || $contact->account?->owner_id === $user->id;
    }

    public function delete(User $user, CrmContact $contact): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, CrmContact $contact): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, CrmContact $contact): bool
    {
        return $user->isAdmin();
    }
}
