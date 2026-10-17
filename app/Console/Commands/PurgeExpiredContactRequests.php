<?php

namespace App\Console\Commands;

use App\Models\ContactRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contacts:purge')]
#[Description('Supprime les demandes de contact au-delà de la durée de conservation.')]
class PurgeExpiredContactRequests extends Command
{
    /**
     * Ces demandes ne comportent aucun fichier joint : la purge se limite à
     * la suppression de l'enregistrement, sans passage par le disque.
     */
    public function handle(): int
    {
        $months = (int) config('site.contact.retention_months', 12);
        $cutoff = now()->subMonths($months);

        $contacts = 0;

        ContactRequest::query()
            ->expired($cutoff)
            ->chunkById(100, function ($batch) use (&$contacts): void {
                foreach ($batch as $contact) {
                    $contact->delete();

                    $contacts++;
                }
            });

        $this->info(sprintf(
            '%d demande(s) supprimée(s) — conservation de %d mois.',
            $contacts,
            $months
        ));

        return self::SUCCESS;
    }
}
