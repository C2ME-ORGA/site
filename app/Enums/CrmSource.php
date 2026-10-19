<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CrmSource: string implements HasLabel
{
    case Website = 'site_web';

    case Referral = 'recommandation';

    case TradeShow = 'salon';

    case Prospecting = 'prospection';

    case WordOfMouth = 'bouche_a_oreille';

    case Network = 'reseau';

    case Other = 'autre';

    public function getLabel(): string
    {
        return match ($this) {
            self::Website => 'Site web',
            self::Referral => 'Recommandation',
            self::TradeShow => 'Salon',
            self::Prospecting => 'Prospection',
            self::WordOfMouth => 'Bouche à oreille',
            self::Network => 'Réseau',
            self::Other => 'Autre',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $source) {
            $options[$source->value] = $source->getLabel();
        }

        return $options;
    }
}
