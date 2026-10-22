<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\EmailMessage;
use App\Models\User;
use App\Notifications\InboundEmailReceived;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Illuminate\Support\Collection;

class InboundEmailNotifier
{
    /**
     * Notifie les utilisateurs concernés par un nouvel e-mail entrant.
     *
     * @return int Nombre d'utilisateurs notifiés.
     */
    public function notify(EmailMessage $message): int
    {
        if (! (bool) config('crm.email.notifications', true)) {
            return 0;
        }

        $recipients = $this->recipients($message);

        if ($recipients->isEmpty()) {
            return 0;
        }

        $notification = new InboundEmailReceived($message);

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);

            // Demande aux composants Filament de rafraîchir la cloche et le badge.
            DatabaseNotificationsSent::dispatch($recipient);
        }

        return $recipients->count();
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(EmailMessage $message): Collection
    {
        $mailbox = $message->mailbox;

        if ($mailbox === null) {
            return collect();
        }

        if ($mailbox->is_shared) {
            return User::query()
                ->where('is_active', true)
                ->whereIn('role', [UserRole::Admin->value, UserRole::Commercial->value])
                ->get();
        }

        $owner = $mailbox->user;

        if ($owner === null || ! $owner->is_active) {
            return collect();
        }

        return collect([$owner]);
    }
}
