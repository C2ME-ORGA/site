<?php

namespace App\Console\Commands;

use App\Models\QuoteRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('quotes:purge')]
#[Description('Supprime les demandes de devis et leurs plans au-delà de la durée de conservation.')]
class PurgeExpiredQuoteRequests extends Command
{
    /**
     * Les plans peuvent contenir des données clients : leur suppression
     * du disque précède celle de l'enregistrement.
     */
    public function handle(): int
    {
        $months = (int) config('site.quote.retention_months', 3);
        $cutoff = now()->subMonths($months);

        $quotes = 0;
        $files = 0;

        QuoteRequest::query()
            ->expired($cutoff)
            ->with('files')
            ->chunkById(100, function ($batch) use (&$quotes, &$files): void {
                foreach ($batch as $quote) {
                    foreach ($quote->files as $file) {
                        $file->purge();

                        $files++;
                    }

                    $quote->delete();

                    $quotes++;
                }
            });

        $this->info(sprintf(
            '%d demande(s) supprimée(s), %d fichier(s) purgé(s) — conservation de %d mois.',
            $quotes,
            $files,
            $months
        ));

        return self::SUCCESS;
    }
}
