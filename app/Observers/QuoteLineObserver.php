<?php

namespace App\Observers;

use App\Models\QuoteLine;

class QuoteLineObserver
{
    public function saving(QuoteLine $line): void
    {
        $gross = (float) $line->quantity * (float) $line->unit_price;
        $discount = $gross * ((float) $line->discount_percent / 100);

        $line->total_ht = round($gross - $discount, 2);
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
