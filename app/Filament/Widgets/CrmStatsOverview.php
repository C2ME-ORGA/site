<?php

namespace App\Filament\Widgets;

use App\Enums\OpportunityStage;
use App\Enums\QuoteDocumentStatus;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmOpportunity;
use App\Models\Quote;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class CrmStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();

        $openStageValues = array_map(
            fn (OpportunityStage $stage): string => $stage->value,
            OpportunityStage::openCases(),
        );

        $openOpportunities = CrmOpportunity::query()
            ->when($user !== null, fn (Builder $query) => $query->ownedBy($user))
            ->whereIn('stage', $openStageValues);

        $pipeline = (float) (clone $openOpportunities)->sum('amount_estimated');

        $pendingQuotes = Quote::query()
            ->whereIn('status', [
                QuoteDocumentStatus::Draft->value,
                QuoteDocumentStatus::Sent->value,
            ])
            ->count();

        $overdue = CrmActivity::query()
            ->when($user !== null, fn (Builder $query) => $query->forUser($user))
            ->overdue()
            ->count();

        $followUps = CrmAccount::query()
            ->when($user !== null, fn (Builder $query) => $query->ownedBy($user))
            ->followUpDue()
            ->count();

        $accounts = CrmAccount::query()
            ->when($user !== null, fn (Builder $query) => $query->ownedBy($user))
            ->count();

        return [
            Stat::make('Comptes', (string) $accounts)
                ->description('Tous carnets')
                ->descriptionIcon('heroicon-o-building-office')
                ->color('primary'),

            Stat::make('Affaires ouvertes', (string) (clone $openOpportunities)->count())
                ->description('Pipeline '.number_format($pipeline, 0, ',', ' ').' €')
                ->descriptionIcon('heroicon-o-briefcase')
                ->color('info'),

            Stat::make('Devis en attente', (string) $pendingQuotes)
                ->description('Brouillons et envoyés')
                ->descriptionIcon('heroicon-o-document-text')
                ->color('warning'),

            Stat::make('Relances dues', (string) $followUps)
                ->description('Comptes à recontacter')
                ->descriptionIcon('heroicon-o-arrow-path')
                ->color($followUps > 0 ? 'danger' : 'success'),

            Stat::make('Actions en retard', (string) $overdue)
                ->description('À traiter sans attendre')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color($overdue > 0 ? 'danger' : 'success'),
        ];
    }
}
