<?php

namespace App\Filament\Pages;

use App\Models\CrmActivity;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class Calendar extends Page
{
    protected string $view = 'filament.pages.calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 17;

    protected static ?string $navigationLabel = 'Calendrier';

    protected static ?string $title = 'Calendrier des relances';

    /**
     * Vue active : « month », « week » ou « day ».
     */
    public string $mode = 'month';

    /**
     * Date d'ancrage (Y-m-d) : sert de repère à la vue affichée.
     */
    public string $date = '';

    public function mount(): void
    {
        $mode = request()->query('view');
        $this->mode = is_string($mode) && in_array($mode, ['month', 'week', 'day'], true)
            ? $mode
            : (string) config('crm.calendar.default_view', 'month');

        if (! in_array($this->mode, ['month', 'week', 'day'], true)) {
            $this->mode = 'month';
        }

        $date = request()->query('date');
        $month = request()->query('month');

        $this->date = $this->normalizeDate(
            is_string($date) ? $date : null,
            is_string($month) ? $month : null,
        );
    }

    public function getSubheading(): string|Htmlable|null
    {
        $anchor = $this->anchor();

        return match ($this->mode) {
            'week' => 'Semaine du '
                .$anchor->startOfWeek(CarbonImmutable::MONDAY)->format('j')
                .' au '.$this->longDate($anchor->endOfWeek(CarbonImmutable::SUNDAY)),
            'day' => ucfirst($this->longDate($anchor)),
            default => $this->monthLabel($anchor),
        };
    }

    public function previousUrl(): string
    {
        return static::getUrl(['view' => $this->mode, 'date' => $this->shift(-1)]);
    }

    public function nextUrl(): string
    {
        return static::getUrl(['view' => $this->mode, 'date' => $this->shift(1)]);
    }

    public function todayUrl(): string
    {
        return static::getUrl(['view' => $this->mode, 'date' => now()->format('Y-m-d')]);
    }

    public function viewUrl(string $mode): string
    {
        return static::getUrl(['view' => $mode, 'date' => $this->date]);
    }

    public function shift(int $delta): string
    {
        $anchor = $this->anchor();

        $target = match ($this->mode) {
            'week' => $anchor->addWeeks($delta),
            'day' => $anchor->addDays($delta),
            default => $anchor->addMonths($delta),
        };

        return $target->format('Y-m-d');
    }

    /**
     * Grille du mois : semaines de 7 jours, du lundi au dimanche.
     *
     * @return array<int, array<int, array{date: string, day: int, inMonth: bool, isToday: bool, activities: array<int, array<string, mixed>>}>>
     */
    public function weeks(): array
    {
        $start = $this->anchor()->startOfMonth();
        $end = $start->endOfMonth();

        $gridStart = $start->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $end->endOfWeek(CarbonImmutable::SUNDAY);

        $activities = $this->activitiesBetween($gridStart, $gridEnd);

        $weeks = [];
        $week = [];
        $cursor = $gridStart;

        while ($cursor <= $gridEnd) {
            $key = $cursor->format('Y-m-d');
            $week[] = [
                'date' => $key,
                'day' => (int) $cursor->format('j'),
                'inMonth' => $cursor->month === $start->month,
                'isToday' => $cursor->isToday(),
                'activities' => $activities[$key] ?? [],
            ];

            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }

            $cursor = $cursor->addDay();
        }

        return $weeks;
    }

    /**
     * Sept jours de la semaine contenant la date d'ancrage.
     *
     * @return array<int, array{date: string, day: int, weekday: string, isToday: bool, activities: array<int, array<string, mixed>>}>
     */
    public function weekDays(): array
    {
        $start = $this->anchor()->startOfWeek(CarbonImmutable::MONDAY);
        $end = $start->endOfWeek(CarbonImmutable::SUNDAY);

        $activities = $this->activitiesBetween($start, $end);

        $days = [];
        $cursor = $start;

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $days[] = [
                'date' => $key,
                'day' => (int) $cursor->format('j'),
                'weekday' => $this->weekdayLabel((int) $cursor->format('N')),
                'isToday' => $cursor->isToday(),
                'activities' => $activities[$key] ?? [],
            ];

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    /**
     * Activités de la journée d'ancrage.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dayActivities(): array
    {
        $anchor = $this->anchor();
        $key = $anchor->format('Y-m-d');

        return $this->activitiesBetween($anchor, $anchor)[$key] ?? [];
    }

    private function anchor(): CarbonImmutable
    {
        $year = (int) substr($this->date, 0, 4);
        $month = (int) substr($this->date, 5, 2);
        $day = (int) substr($this->date, 8, 2);

        return now()->toImmutable()->setDate($year, $month, $day)->startOfDay();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function activitiesBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $user = auth()->user();

        $activities = CrmActivity::query()
            ->when($user !== null, fn ($query) => $query->forUser($user))
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$from->startOfDay(), $to->endOfDay()])
            ->with('account')
            ->orderBy('due_at')
            ->get();

        $grouped = [];

        foreach ($activities as $activity) {
            $dueAt = $activity->due_at;

            if ($dueAt === null) {
                continue;
            }

            $key = $dueAt->format('Y-m-d');
            $grouped[$key][] = [
                'id' => $activity->id,
                'time' => $dueAt->format('H:i'),
                'label' => $activity->type->getLabel(),
                'color' => $activity->type->getColor(),
                'subject' => $activity->subject,
                'account' => $activity->account?->name,
                'overdue' => $activity->isOverdue(),
            ];
        }

        return $grouped;
    }

    private function normalizeDate(?string $date, ?string $month): string
    {
        if ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return $date;
        }

        if ($month !== null && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return $month.'-01';
        }

        return now()->format('Y-m-d');
    }

    private function monthLabel(CarbonImmutable $date): string
    {
        return $this->monthName((int) $date->format('n')).' '.$date->format('Y');
    }

    private function longDate(CarbonImmutable $date): string
    {
        return $date->format('j').' '.$this->monthName((int) $date->format('n')).' '.$date->format('Y');
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
        ][$month];
    }

    private function weekdayLabel(int $isoDay): string
    {
        return [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche',
        ][$isoDay];
    }
}
