<?php

namespace App\Policies;

use App\Models\EmailMessage;
use App\Models\User;

class EmailMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCommercial();
    }

    public function view(User $user, EmailMessage $message): bool
    {
        $mailbox = $message->mailbox;

        return $user->isAdmin()
            || ($mailbox !== null && ($mailbox->is_shared || $mailbox->user_id === $user->id));
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isCommercial();
    }

    public function update(User $user, EmailMessage $message): bool
    {
        return $this->view($user, $message);
    }

    public function delete(User $user, EmailMessage $message): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
