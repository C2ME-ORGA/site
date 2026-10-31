<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\DashboardMetricsService;
use Elemind\FilamentECharts\Widgets\EChartWidget;
use Filament\Support\RawJs;

class PipelineByBookChart extends EChartWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected static ?string $chartId = 'pipelineByBookChart';

    protected static ?string $heading = 'Pipeline par carnet';

    protected static ?string $subheading = 'Affaires ouvertes, montant brut et pondéré par la probabilité.';

    protected ?string $pollingInterval = null;

    protected static int $contentHeight = 320;

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $rows = app(DashboardMetricsService::class)->pipelineByBook($user);

        return [
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => ['type' => 'shadow'],
            ],
            'legend' => [
                'bottom' => 0,
                'itemWidth' => 12,
                'itemHeight' => 12,
            ],
            'grid' => [
                'left' => 8,
                'right' => 12,
                'top' => 16,
                'bottom' => 62,
                'containLabel' => true,
            ],
            'xAxis' => [
                'type' => 'category',
                'data' => array_map(
                    fn (array $row): string => sprintf('%s (%d)', $row['book']->shortLabel(), $row['count']),
                    $rows,
                ),
                'axisLabel' => ['interval' => 0, 'fontSize' => 11],
            ],
            'yAxis' => [
                'type' => 'value',
                'splitLine' => ['lineStyle' => ['type' => 'dashed']],
            ],
            'series' => [
                [
                    'name' => 'Pipeline brut',
                    'type' => 'bar',
                    'data' => array_map(fn (array $row): float => round($row['raw']), $rows),
                    'itemStyle' => ['color' => '#0b7c7c', 'borderRadius' => [4, 4, 0, 0]],
                    'barMaxWidth' => 28,
                ],
                [
                    'name' => 'Pipeline pondéré',
                    'type' => 'bar',
                    'data' => array_map(fn (array $row): float => round($row['weighted']), $rows),
                    'itemStyle' => ['color' => '#bde950', 'borderRadius' => [4, 4, 0, 0]],
                    'barMaxWidth' => 28,
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
