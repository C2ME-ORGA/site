<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Support\DashboardFormat;
use Filament\Widgets\ChartWidget;

class PipelineByBookChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Pipeline par carnet';

    protected ?string $description = 'Affaires ouvertes à date, en montant brut et pondéré par la probabilité.';

    /*
     * Pas de `$maxHeight` : Filament dimensionne alors le cadre du graphique
     * avec le ratio d'aspect natif (hauteur définitive), au lieu d'un
     * `max-height` posé sur un canvas en `height: 100%` dont la hauteur
     * dépendait du parent lui-même. Voir `theme.css` pour la hauteur visuelle.
     */

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [
                'labels' => [],
                'datasets' => [],
            ];
        }

        $rows = app(DashboardMetricsService::class)->pipelineByBook($user);

        return [
            'labels' => array_map(
                fn (array $row): string => sprintf(
                    '%s (%d · %s)',
                    $row['book']->shortLabel(),
                    $row['count'],
                    DashboardFormat::percent($row['share']),
                ),
                $rows,
            ),
            'datasets' => [
                [
                    'label' => 'Pipeline brut',
                    'data' => array_map(fn (array $row): float => round($row['raw'], 2), $rows),
                    'backgroundColor' => '#0e8577',
                ],
                [
                    'label' => 'Pipeline pondéré',
                    'data' => array_map(fn (array $row): float => round($row['weighted'], 2), $rows),
                    'backgroundColor' => '#bde950',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }
}
