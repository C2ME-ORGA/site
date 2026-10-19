<?php

namespace App\Policies;

use App\Models\CrmActivity;
use App\Models\User;

class CrmActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, CrmActivity $activity): bool
    {
        return $user->isAdmin()
            || $activity->user_id === $user->id
            || $activity->account?->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, CrmActivity $activity): bool
    {
        return $user->isAdmin()
            || $activity->user_id === $user->id
            || $activity->account?->owner_id === $user->id;
    }

    public function delete(User $user, CrmActivity $activity): bool
    {
        return $user->isAdmin() || $activity->user_id === $user->id;
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
