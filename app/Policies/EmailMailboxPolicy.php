<?php

namespace App\Policies;

use App\Models\EmailMailbox;
use App\Models\User;

class EmailMailboxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, EmailMailbox $mailbox): bool
    {
        return $user->isAdmin()
            || $mailbox->is_shared
            || $mailbox->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, EmailMailbox $mailbox): bool
    {
        return $user->isAdmin() || $mailbox->user_id === $user->id;
    }

    public function delete(User $user, EmailMailbox $mailbox): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
