<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Support\DashboardPeriod;
use Elemind\FilamentECharts\Widgets\EChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;

class QuoteConversionGauge extends EChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    protected static ?string $chartId = 'quoteConversionGauge';

    protected static ?string $heading = 'Taux de conversion des devis';

    protected ?string $pollingInterval = null;

    protected static int $contentHeight = 320;

    protected function getSubheading(): null|string|Htmlable|View
    {
        return 'Devis acceptés sur devis envoyés · '.$this->period()->label;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $period = $this->period();
        $rate = app(DashboardMetricsService::class)->quoteConversionRate($user, $period);
        $value = $rate === null ? 0.0 : round($rate, 1);

        return [
            'series' => [
                [
                    'type' => 'gauge',
                    'startAngle' => 210,
                    'endAngle' => -30,
                    'min' => 0,
                    'max' => 100,
                    'splitNumber' => 4,
                    'radius' => '88%',
                    'center' => ['50%', '58%'],
                    'progress' => [
                        'show' => true,
                        'width' => 14,
                        'itemStyle' => ['color' => $rate === null ? '#d3dbdb' : '#0b7c7c'],
                    ],
                    'axisLine' => [
                        'lineStyle' => ['width' => 14, 'color' => [[1, 'rgba(128, 140, 140, 0.22)']]],
                    ],
                    'axisTick' => ['show' => false],
                    'splitLine' => ['show' => false],
                    'axisLabel' => ['distance' => 18, 'fontSize' => 10, 'formatter' => '{value} %'],
                    'pointer' => ['show' => false],
                    'anchor' => ['show' => false],
                    'title' => ['show' => false],
                    'detail' => [
                        'valueAnimation' => true,
                        'formatter' => $rate === null ? '—' : '{value} %',
                        'fontSize' => 30,
                        'fontWeight' => 'bold',
                        'offsetCenter' => [0, '18%'],
                        'color' => '#29b2b2',
                    ],
                    'data' => [['value' => $value]],
                ],
            ],
        ];
    }

    private function period(): DashboardPeriod
    {
        return DashboardPeriod::fromFilters($this->pageFilters);
    }
}
