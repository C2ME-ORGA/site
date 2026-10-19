<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AccountPotential: string implements HasColor, HasLabel
{
    case A = 'a';

    case B = 'b';

    case C = 'c';

    public function getLabel(): string
    {
        return match ($this) {
            self::A => 'A — Fort',
            self::B => 'B — Moyen',
            self::C => 'C — Faible',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::A => 'success',
            self::B => 'warning',
            self::C => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $potential) {
            $options[$potential->value] = $potential->getLabel();
        }

        return $options;
    }
}
