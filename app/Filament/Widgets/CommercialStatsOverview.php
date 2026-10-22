<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Support\DashboardFormat;
use App\Support\DashboardPeriod;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommercialStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $period = DashboardPeriod::fromFilters($this->pageFilters);
        $metrics = app(DashboardMetricsService::class);

        $quoteRevenue = $metrics->signedQuoteRevenue($user, $period);
        $acceptedQuotes = $metrics->acceptedQuotesCount($user, $period);
        $wonRevenue = $metrics->wonOpportunityRevenue($user, $period);
        $wonOpportunities = $metrics->wonOpportunitiesCount($user, $period);
        $conversion = $metrics->quoteConversionRate($user, $period);
        $sentQuotes = $metrics->sentQuotesCount($user, $period);
        $averageQuote = $metrics->averageSignedQuote($user, $period);
        $averageWon = $metrics->averageWonOpportunity($user, $period);

        return [
            Stat::make('CA signé — devis', DashboardFormat::money($quoteRevenue))
                ->description($acceptedQuotes.' devis accepté(s) · '.$period->label)
                ->descriptionIcon('heroicon-o-document-check')
                ->color('success'),

            Stat::make('CA gagné — affaires', DashboardFormat::money($wonRevenue))
                ->description($wonOpportunities.' affaire(s) gagnée(s) · '.$period->label)
                ->descriptionIcon('heroicon-o-trophy')
                ->color('success'),

            Stat::make('Taux de conversion devis', DashboardFormat::percent($conversion))
                ->description(
                    $conversion === null
                        ? 'Aucun devis envoyé sur la période'
                        : $sentQuotes.' devis envoyé(s) sur la période',
                )
                ->descriptionIcon('heroicon-o-arrow-trending-up')
                ->color('info'),

            Stat::make('Panier moyen', DashboardFormat::money($averageQuote))
                ->description(
                    $averageWon === null
                        ? 'Aucune affaire gagnée sur la période'
                        : 'Affaires : '.DashboardFormat::money($averageWon),
                )
                ->descriptionIcon('heroicon-o-calculator')
                ->color('primary'),
        ];
    }
}
