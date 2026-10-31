<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\DashboardMetricsService;
use Elemind\FilamentECharts\Widgets\EChartWidget;
use Filament\Support\RawJs;

class RevenueTrendChart extends EChartWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $chartId = 'revenueTrendChart';

    protected static ?string $heading = 'Chiffre d’affaires — 12 derniers mois';

    protected static ?string $subheading = 'Devis acceptés et affaires gagnées, hors taxes.';

    protected ?string $pollingInterval = null;

    protected static int $contentHeight = 340;

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $data = app(DashboardMetricsService::class)->revenueByMonth($user);

        if (array_sum($data['quotes']) === 0.0 && array_sum($data['opportunities']) === 0.0) {
            return [
                'title' => [
                    'text' => 'Aucun CA signé ou gagné sur les 12 derniers mois',
                    'left' => 'center',
                    'top' => 'middle',
                    'textStyle' => ['fontSize' => 13, 'fontWeight' => 'normal', 'color' => '#6b7f80'],
                ],
                // Axes masqués mais déclarés : `extraJsOptions()` les complète.
                'xAxis' => ['type' => 'category', 'show' => false, 'data' => []],
                'yAxis' => ['type' => 'value', 'show' => false],
                'series' => [],
            ];
        }

        return [
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => ['type' => 'cross'],
            ],
            'legend' => [
                'bottom' => 0,
                'data' => ['Devis acceptés', 'Affaires gagnées'],
                'itemWidth' => 12,
                'itemHeight' => 12,
            ],
            'grid' => [
                'left' => 8,
                'right' => 16,
                'top' => 24,
                'bottom' => 44,
                'containLabel' => true,
            ],
            'xAxis' => [
                'type' => 'category',
                'data' => $data['labels'],
                'axisLabel' => ['interval' => 0, 'rotate' => 30, 'fontSize' => 11],
            ],
            'yAxis' => [
                'type' => 'value',
                'splitLine' => ['lineStyle' => ['type' => 'dashed']],
            ],
            'series' => [
                [
                    'name' => 'Devis acceptés',
                    'type' => 'bar',
                    'data' => $data['quotes'],
                    'itemStyle' => ['color' => '#0b7c7c', 'borderRadius' => [4, 4, 0, 0]],
                    'barMaxWidth' => 24,
                ],
                [
                    'name' => 'Affaires gagnées',
                    'type' => 'line',
                    'smooth' => true,
                    'symbolSize' => 7,
                    'data' => $data['opportunities'],
                    'itemStyle' => ['color' => '#a2cf35'],
                    'lineStyle' => ['width' => 3, 'color' => '#a2cf35'],
                ],
            ],
        ];
    }

    protected function extraJsOptions(): ?RawJs
    {
        return RawJs::make(<<<'JS'
        {
            yAxis: {
                axisLabel: {
                    formatter: function (value) {
                        return new Intl.NumberFormat('fr-FR', {
                            notation: 'compact',
                            maximumFractionDigits: 1,
                        }).format(value) + ' €';
                    }
                }
            },
            tooltip: {
                valueFormatter: function (value) {
                    return new Intl.NumberFormat('fr-FR', {
                        style: 'currency',
                        currency: 'EUR',
                        maximumFractionDigits: 0,
                    }).format(value);
                }
            }
        }
        JS);
    }
}
