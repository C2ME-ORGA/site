<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AccountStatus: string implements HasColor, HasLabel
{
    case Prospect = 'prospect';

    case Client = 'client';

    case Partner = 'partenaire';

    case Inactive = 'inactif';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prospect => 'Prospect',
            self::Client => 'Client',
            self::Partner => 'Partenaire',
            self::Inactive => 'Inactif',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Prospect => 'warning',
            self::Client => 'success',
            self::Partner => 'info',
            self::Inactive => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->getLabel();
        }

        return $options;
    }
}
