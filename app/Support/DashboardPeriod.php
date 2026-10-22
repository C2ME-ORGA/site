<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

final readonly class DashboardPeriod
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public string $label,
        public bool $isCustom = false,
    ) {}

    /**
     * @param  array<string, mixed>|null  $filters
     */
    public static function fromFilters(?array $filters): self
    {
        $filters ??= [];
        $period = $filters['period'] ?? 'year';
        $year = self::validYear($filters['year'] ?? null);

        return match ($period) {
            'month' => self::month($year, self::validMonth($filters['month'] ?? null)),
            'quarter' => self::quarter($year, self::validQuarter($filters['quarter'] ?? null)),
            'custom' => self::custom($filters['from'] ?? null, $filters['to'] ?? null),
            default => self::year($year),
        };
    }

    public static function year(int $year): self
    {
        $start = CarbonImmutable::create($year, 1, 1, 0, 0, 0, config('app.timezone'));
        $end = CarbonImmutable::create($year, 12, 31, 23, 59, 59, config('app.timezone'));

        return new self($start, $end, 'Année '.$year);
    }

    public static function month(int $year, int $month): self
    {
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, config('app.timezone'));
        $end = $start->endOfMonth();

        $label = self::monthNames()[$month].' '.$year;

        return new self($start, $end, $label);
    }

    public static function quarter(int $year, int $quarter): self
    {
        $startMonth = (($quarter - 1) * 3) + 1;
        $start = CarbonImmutable::create($year, $startMonth, 1, 0, 0, 0, config('app.timezone'));
        $end = $start->addMonths(2)->endOfMonth();

        return new self($start, $end, 'T'.$quarter.' '.$year);
    }

    public static function custom(mixed $from, mixed $to): self
    {
        if (! is_string($from) || ! is_string($to) || $from === '' || $to === '') {
            return self::year((int) now()->year);
        }

        try {
            $start = CarbonImmutable::parse($from, config('app.timezone'))->startOfDay();
            $end = CarbonImmutable::parse($to, config('app.timezone'))->endOfDay();
        } catch (Throwable) {
            return self::year((int) now()->year);
        }

        if ($end->lessThan($start)) {
            return self::year((int) now()->year);
        }

        return new self($start, $end, $start->format('d/m/Y').' → '.$end->format('d/m/Y'), true);
    }

    public function contains(CarbonImmutable $date): bool
    {
        return $date->betweenIncluded($this->start, $this->end);
    }

    public function previousPeriod(): self
    {
        $days = (int) $this->start->diffInDays($this->end) + 1;

        return new self(
            $this->start->subDays($days)->startOfDay(),
            $this->start->subDay()->endOfDay(),
            'Période précédente',
        );
    }

    public function monthStart(): CarbonImmutable
    {
        return $this->start->startOfMonth();
    }

    public function monthEnd(): CarbonImmutable
    {
        return $this->end->endOfMonth();
    }

    private static function validYear(mixed $year): int
    {
        $year = is_numeric($year) ? (int) $year : (int) now()->year;

        return $year >= 2000 && $year <= 2100 ? $year : (int) now()->year;
    }

    private static function validMonth(mixed $month): int
    {
        $month = is_numeric($month) ? (int) $month : (int) now()->month;

        return $month >= 1 && $month <= 12 ? $month : (int) now()->month;
    }

    private static function validQuarter(mixed $quarter): int
    {
        $quarter = is_numeric($quarter) ? (int) $quarter : (int) ceil(now()->month / 3);

        return $quarter >= 1 && $quarter <= 4 ? $quarter : (int) ceil(now()->month / 3);
    }

    /**
     * @return array<int, string>
     */
    public static function monthNames(): array
    {
        return [
            1 => 'Janvier',
            2 => 'Février',
            3 => 'Mars',
            4 => 'Avril',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juillet',
            8 => 'Août',
            9 => 'Septembre',
            10 => 'Octobre',
            11 => 'Novembre',
            12 => 'Décembre',
        ];
    }
}
