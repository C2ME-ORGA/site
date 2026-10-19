<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->isAdmin() || $quote->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->isAdmin() || $quote->owner_id === $user->id;
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, Quote $quote): bool
    {
        return $user->isAdmin();
    }

    public function forceDelete(User $user, Quote $quote): bool
    {
        return $user->isAdmin();
    }
}
