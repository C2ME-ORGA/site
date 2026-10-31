<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\DashboardMetricsService;
use Elemind\FilamentECharts\Widgets\EChartWidget;
use Filament\Support\RawJs;

class TopAccountsPipelineChart extends EChartWidget
{
    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    protected static ?string $chartId = 'topAccountsPipelineChart';

    protected static ?string $heading = 'Top 5 comptes par pipeline';

    protected static ?string $subheading = 'Montant des affaires ouvertes, par compte.';

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

        $data = app(DashboardMetricsService::class)->topAccountsByPipeline($user);

        if ($data['labels'] === []) {
            return [
                'title' => [
                    'text' => 'Aucune affaire ouverte',
                    'left' => 'center',
                    'top' => 'middle',
                    'textStyle' => ['fontSize' => 13, 'fontWeight' => 'normal', 'color' => '#6b7f80'],
                ],
                // Axes et série masqués mais déclarés : `extraJsOptions()` les complète.
                'xAxis' => ['type' => 'value', 'show' => false],
                'yAxis' => ['type' => 'category', 'show' => false, 'data' => []],
                'series' => [['type' => 'bar', 'data' => []]],
            ];
        }

        return [
            'tooltip' => ['trigger' => 'axis', 'axisPointer' => ['type' => 'shadow']],
            'grid' => [
                'left' => 8,
                'right' => 48,
                'top' => 16,
                'bottom' => 8,
                'containLabel' => true,
            ],
            'xAxis' => [
                'type' => 'value',
                'splitLine' => ['lineStyle' => ['type' => 'dashed']],
            ],
            'yAxis' => [
                'type' => 'category',
                // ECharts affiche le premier élément en bas : on inverse pour
                // garder le plus gros compte en haut.
                'data' => array_reverse($data['labels']),
                'axisLabel' => ['width' => 130, 'overflow' => 'truncate', 'fontSize' => 11],
            ],
            'series' => [
                [
                    'name' => 'Pipeline ouvert',
                    'type' => 'bar',
                    'data' => array_reverse($data['values']),
                    'itemStyle' => ['color' => '#0b7c7c', 'borderRadius' => [0, 4, 4, 0]],
                    'barMaxWidth' => 22,
                    'label' => [
                        'show' => true,
                        'position' => 'right',
                        'fontSize' => 10,
                    ],
                ],
            ],
        ];
    }

    protected function extraJsOptions(): ?RawJs
    {
        return RawJs::make(<<<'JS'
        {
            xAxis: {
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
            },
            series: [
                {
                    label: {
                        formatter: function (params) {
                            return new Intl.NumberFormat('fr-FR', {
                                notation: 'compact',
                                maximumFractionDigits: 1,
                            }).format(params.value) + ' €';
                        }
                    }
                }
            ]
        }
        JS);
    }
}
