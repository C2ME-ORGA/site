<?php

namespace App\Observers;

use App\Models\QuoteLine;

class QuoteLineObserver
{
    public function saving(QuoteLine $line): void
    {
        // Les champs financiers sont obligatoires en base : un champ vidé dans
        // le formulaire est ramené à zéro plutôt que d'être enregistré à null.
        $line->quantity = QuoteLine::normalizeNumber($line->quantity);
        $line->unit_price = QuoteLine::normalizeNumber($line->unit_price);
        $line->discount_percent = QuoteLine::normalizeNumber($line->discount_percent);

        $line->total_ht = QuoteLine::calculateTotalHt(
            $line->quantity,
            $line->unit_price,
            $line->discount_percent,
        );
    }

    public function saved(QuoteLine $line): void
    {
        $line->quote?->recalculateTotals();
    }

    public function deleted(QuoteLine $line): void
    {
        $line->quote?->recalculateTotals();
    }
}
