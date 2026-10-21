<?php

namespace App\Services;

use App\Enums\FollowUpReminderStage;
use App\Mail\FollowUpDigestMail;
use App\Models\CrmFollowUpReminder;
use App\Models\User;
use App\Notifications\FollowUpDigest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class FollowUpNotifier
{
    /**
     * Envoie le digest in-app puis l'e-mail. Un échec d'un canal n'empêche
     * jamais l'envoi de l'autre. Chaque canal ne reçoit que les rappels
     * encore en attente pour ce canal.
     *
     * @param  Collection<int, CrmFollowUpReminder>  $databaseReminders
     * @param  Collection<int, CrmFollowUpReminder>  $mailReminders
     */
    public function send(
        User $user,
        Collection $databaseReminders,
        Collection $mailReminders,
        FollowUpReminderStage $stage,
    ): FollowUpDeliveryResult {
        $databaseSent = true;
        $databaseError = null;

        if ($databaseReminders->isNotEmpty()) {
            try {
                Notification::send($user, new FollowUpDigest($stage, $databaseReminders));
            } catch (Throwable $exception) {
                report($exception);
                $databaseSent = false;
                $databaseError = $this->errorMessage($exception);
            }
        }

        if ($mailReminders->isEmpty()) {
            return new FollowUpDeliveryResult(
                databaseSent: $databaseSent,
                mailSent: true,
                databaseError: $databaseError,
            );
        }

        if (! (bool) config('crm.follow_ups.mail_enabled', true)) {
            return new FollowUpDeliveryResult(
                databaseSent: $databaseSent,
                mailSent: true,
                mailSkipped: true,
                databaseError: $databaseError,
            );
        }

        $mailSent = false;
        $mailError = null;

        try {
            Mail::to($user->email)->send(new FollowUpDigestMail($user, $mailReminders, $stage));
            $mailSent = true;
        } catch (Throwable $exception) {
            report($exception);
            $mailError = $this->errorMessage($exception);
        }

        return new FollowUpDeliveryResult(
            databaseSent: $databaseSent,
            mailSent: $mailSent,
            databaseError: $databaseError,
            mailError: $mailError,
        );
    }

    private function errorMessage(Throwable $exception): string
    {
        return Str::limit($exception->getMessage(), 1000);
    }
}
