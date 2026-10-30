<?php

namespace App\Console\Commands;

use App\Models\CrmActivity;
use App\Models\CrmActivityAttachment;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('crm:purge-activities')]
#[Description('Supprime les activités au-delà de la durée de conservation.')]
class PurgeOldActivities extends Command
{
    public function handle(): int
    {
        $months = (int) config('crm.retention.activities_months', 60);
        $cutoff = now()->subMonths($months);

        $query = CrmActivity::query()
            ->where(fn (Builder $query) => $query
                ->where('occurred_at', '<', $cutoff)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('occurred_at')
                    ->where('updated_at', '<', $cutoff)));

        // Les photos sont supprimées du disque avant la suppression en masse :
        // un delete() de requête ne déclenche pas les évènements du modèle.
        CrmActivityAttachment::query()
            ->whereIn('crm_activity_id', (clone $query)->select('id'))
            ->get()
            ->each(fn (CrmActivityAttachment $attachment) => $attachment->delete());

        $deleted = (clone $query)->delete();

        $this->info(sprintf(
            '%d activité(s) supprimée(s) — conservation de %d mois.',
            $deleted,
            $months
        ));

        return self::SUCCESS;
    }
}
