<?php

namespace App\Console\Commands;

use App\Enums\QuoteDocumentStatus;
use App\Models\Quote;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('quotes:expire')]
#[Description('Marque comme expirés les devis envoyés dont la date de validité est dépassée.')]
class ExpireQuotes extends Command
{
    public function handle(): int
    {
        $expired = 0;

        Quote::query()
            ->where('status', QuoteDocumentStatus::Sent->value)
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', now()->toDateString())
            ->chunkById(100, function ($quotes) use (&$expired): void {
                foreach ($quotes as $quote) {
                    if ($quote->transitionTo(QuoteDocumentStatus::Expired)) {
                        $expired++;
                    }
                }
            });

        $this->info(sprintf('%d devis expiré(s).', $expired));

        return self::SUCCESS;
    }
}
