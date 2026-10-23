<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CrmTagColor: string implements HasColor, HasLabel
{
    case Gray = 'gray';

    case Danger = 'danger';

    case Info = 'info';

    case Success = 'success';

    case Warning = 'warning';

    case Primary = 'primary';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gray => 'Gris',
            self::Danger => 'Rouge',
            self::Info => 'Bleu',
            self::Success => 'Vert',
            self::Warning => 'Ambre',
            self::Primary => 'Principal',
        };
    }

    public function getColor(): string
    {
        return $this->value;
    }

    /**
     * Teinte pastelle utilisée pour les gabarits imprimés, qui n'ont pas accès
     * aux classes Tailwind du panel.
     */
    public function printTint(): string
    {
        return match ($this) {
            self::Gray => '#6b7280',
            self::Danger => '#b91c1c',
            self::Info => '#1d4ed8',
            self::Success => '#15803d',
            self::Warning => '#b45309',
            self::Primary => '#92400e',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $color) {
            $options[$color->value] = $color->getLabel();
        }

        return $options;
    }
}
