<?php

namespace App\Support;

final class DashboardFormat
{
    public static function money(float|int|string|null $amount): string
    {
        return number_format((float) ($amount ?? 0), 0, ',', ' ').' €';
    }

    public static function percent(float|int|null $rate): string
    {
        if ($rate === null) {
            return '—';
        }

        return number_format((float) $rate, 1, ',', ' ').' %';
    }
}
