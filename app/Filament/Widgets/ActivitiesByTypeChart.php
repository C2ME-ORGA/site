<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\DashboardMetricsService;
use App\Support\DashboardPeriod;
use Elemind\FilamentECharts\Widgets\EChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;

class ActivitiesByTypeChart extends EChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 1;

    protected static ?string $chartId = 'activitiesByTypeChart';

    protected static ?string $heading = 'Activités par type';

    protected ?string $pollingInterval = null;

    protected static int $contentHeight = 320;

    protected function getSubheading(): null|string|Htmlable|View
    {
        return 'Activités enregistrées · '.$this->period()->label;
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

        $data = app(DashboardMetricsService::class)->activitiesByType($user, $this->period());

        if ($data['labels'] === []) {
            return [
                'title' => [
                    'text' => 'Aucune activité sur la période',
                    'left' => 'center',
                    'top' => 'middle',
                    'textStyle' => ['fontSize' => 13, 'fontWeight' => 'normal', 'color' => '#6b7f80'],
                ],
            ];
        }

        $colors = ['#0b7c7c', '#29b2b2', '#58c8c8', '#a2cf35', '#bde950', '#0d2b33', '#90dcdc'];

        $series = [];

        foreach ($data['labels'] as $index => $label) {
            $series[] = [
                'name' => $label,
                'value' => $data['values'][$index],
                'itemStyle' => ['color' => $colors[$index % count($colors)]],
            ];
        }

        return [
            'tooltip' => ['trigger' => 'item', 'formatter' => '{b} : {c} ({d} %)'],
            'legend' => [
                'bottom' => 0,
                'itemWidth' => 12,
                'itemHeight' => 12,
            ],
            'series' => [
                [
                    'type' => 'pie',
                    'radius' => ['42%', '68%'],
                    'center' => ['50%', '44%'],
                    'avoidLabelOverlap' => true,
                    'itemStyle' => [
                        'borderRadius' => 6,
                        'borderColor' => 'transparent',
                        'borderWidth' => 2,
                    ],
                    'label' => [
                        'show' => true,
                        'fontSize' => 11,
                        'formatter' => '{b} : {c}',
                    ],
                    'labelLine' => ['length' => 8, 'length2' => 8],
                    'data' => $series,
                ],
            ],
        ];
    }

    private function period(): DashboardPeriod
    {
        return DashboardPeriod::fromFilters($this->pageFilters);
    }
}
