<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Support\DashboardFormat;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthlyTargetOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $data = app(DashboardMetricsService::class)->monthlyTargetProgress($user);

        if ($data['target'] <= 0) {
            return [
                Stat::make('Objectif mensuel', 'Non défini')
                    ->description('Renseignez CRM_DASHBOARD_MONTHLY_TARGET pour activer la jauge.')
                    ->descriptionIcon('heroicon-o-flag')
                    ->color('gray'),
            ];
        }

        $progress = max(0.0, (float) $data['progress']);
        $color = match (true) {
            $progress >= 100 => 'success',
            $progress >= 70 => 'warning',
            default => 'danger',
        };

        return [
            Stat::make(
                'Objectif mensuel — '.$data['period']->label,
                DashboardFormat::money($data['revenue']).' / '.DashboardFormat::money($data['target']),
            )
                ->description(
                    $progress >= 100
                        ? 'Objectif atteint à '.DashboardFormat::percent($progress)
                        : DashboardFormat::percent($progress).' atteint · reste '.DashboardFormat::money($data['remaining']),
                )
                ->descriptionIcon($progress >= 100 ? 'heroicon-o-check-badge' : 'heroicon-o-flag')
                ->color($color),
        ];
    }
}
