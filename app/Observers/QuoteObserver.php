<?php

namespace App\Observers;

use App\Enums\QuoteDocumentStatus;
use App\Models\Quote;
use App\Services\QuoteNotifier;
use DomainException;

class QuoteObserver
{
    public function __construct(private readonly QuoteNotifier $notifier) {}

    public function creating(Quote $quote): void
    {
        $quote->reference ??= Quote::nextReference();
        $quote->status ??= QuoteDocumentStatus::Draft;
    }

    public function saving(Quote $quote): void
    {
        // Les montants et le taux de TVA sont obligatoires en base : une valeur
        // absente ou vidée dans le formulaire est ramenée à zéro.
        $quote->amount_ht = is_numeric($quote->amount_ht) ? $quote->amount_ht : 0;
        $quote->vat_rate = is_numeric($quote->vat_rate) ? $quote->vat_rate : 0;

        $quote->amount_ttc = Quote::calculateAmountTtc($quote->amount_ht, $quote->vat_rate);

        if (! $quote->exists || ! $quote->isDirty('status')) {
            return;
        }

        $from = $this->originalStatus($quote);

        if ($from !== null && ! $from->canTransitionTo($quote->status)) {
            throw new DomainException(sprintf(
                'Transition de statut invalide pour le devis %s : %s → %s.',
                (string) $quote->reference,
                $from->value,
                $quote->status->value,
            ));
        }
    }

    public function saved(Quote $quote): void
    {
        if ($quote->status === QuoteDocumentStatus::Sent && $quote->sent_at === null) {
            $quote->forceFill(['sent_at' => now()])->saveQuietly();
        }

        if ($quote->status === QuoteDocumentStatus::Accepted && $quote->accepted_at === null) {
            $quote->forceFill(['accepted_at' => now()])->saveQuietly();
        }

        if ($quote->status === QuoteDocumentStatus::Refused && $quote->refused_at === null) {
            $quote->forceFill(['refused_at' => now()])->saveQuietly();
        }

        $quote->opportunity?->syncFromQuote($quote);
    }

    public function updated(Quote $quote): void
    {
        if (! $quote->wasChanged('status')) {
            return;
        }

        $from = $this->originalStatus($quote);

        if ($from === null) {
            return;
        }

        $this->notifier->notifyStatusChanged($quote, $from, $quote->status);
    }

    private function originalStatus(Quote $quote): ?QuoteDocumentStatus
    {
        $original = $quote->getOriginal('status');

        if ($original instanceof QuoteDocumentStatus) {
            return $original;
        }

        if ($original === null) {
            return null;
        }

        return QuoteDocumentStatus::tryFrom((string) $original);
    }
}
