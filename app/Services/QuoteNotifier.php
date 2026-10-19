<?php

namespace App\Services;

use App\Enums\QuoteDocumentStatus;
use App\Enums\UserRole;
use App\Mail\QuoteStatusChangedMail;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\QuoteStatusChanged;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Throwable;

class QuoteNotifier
{
    /**
     * Notifie les administrateurs actifs et le propriétaire du devis.
     */
    public function notifyStatusChanged(Quote $quote, QuoteDocumentStatus $from, QuoteDocumentStatus $to): void
    {
        $recipients = $this->recipients($quote);

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new QuoteStatusChanged($quote, $from, $to));

        try {
            Mail::to($recipients->pluck('email')->all())
                ->send(new QuoteStatusChangedMail($quote, $from, $to));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(Quote $quote): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($quote): void {
                $query->where('role', UserRole::Admin->value);

                if ($quote->owner_id !== null) {
                    $query->orWhere('id', $quote->owner_id);
                }
            })
            ->get();
    }
}
