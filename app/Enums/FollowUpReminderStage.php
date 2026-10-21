<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FollowUpReminderStage: string implements HasColor, HasLabel
{
    case DayBefore = 'day_before';

    case DueDay = 'due_day';

    public function getLabel(): string
    {
        return match ($this) {
            self::DayBefore => 'Veille de relance',
            self::DueDay => 'Jour de relance',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DayBefore => 'info',
            self::DueDay => 'warning',
        };
    }

    public function notificationTitle(int $count): string
    {
        return match ($this) {
            self::DayBefore => sprintf('Relances de demain (%d)', $count),
            self::DueDay => sprintf('Relances du jour (%d)', $count),
        };
    }

    public function isDueDay(): bool
    {
        return $this === self::DueDay;
    }
}
