<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuoteKind: string implements HasColor, HasLabel
{
    case Laser = 'laser';

    case Building = 'building';

    /**
     * Libellé affiché au visiteur et dans le back-office.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::Laser => 'Découpe laser',
            self::Building => 'Bâtiment & charpente',
        };
    }

    /**
     * Accroche courte présentée au choix des deux parcours.
     */
    public function description(): string
    {
        return match ($this) {
            self::Laser => 'Pièces découpées au laser : matière, épaisseur, quantité et plans.',
            self::Building => 'Structure métallique : dimensions, usage, enveloppe et options.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Laser => 'info',
            self::Building => 'success',
        };
    }

    /**
     * Toutes les valeurs autorisées, dans l'ordre d'affichage.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
