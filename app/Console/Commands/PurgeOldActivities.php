<?php

namespace App\Console\Commands;

use App\Models\CrmActivity;
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

        $deleted = CrmActivity::query()
            ->where(fn (Builder $query) => $query
                ->where('occurred_at', '<', $cutoff)
                ->orWhere(fn (Builder $query) => $query
                    ->whereNull('occurred_at')
                    ->where('updated_at', '<', $cutoff)))
            ->delete();

        $this->info(sprintf(
            '%d activité(s) supprimée(s) — conservation de %d mois.',
            $deleted,
            $months
        ));

        return self::SUCCESS;
    }
}
