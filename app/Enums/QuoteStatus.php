<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuoteStatus: string implements HasColor, HasLabel
{
    case New = 'new';

    case UnderStudy = 'under_study';

    case QuoteSent = 'quote_sent';

    case Accepted = 'accepted';

    case Rejected = 'rejected';

    case Closed = 'closed';

    /**
     * Libellé affiché dans le back-office.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nouveau',
            self::UnderStudy => "En cours d'étude",
            self::QuoteSent => 'Devis envoyé',
            self::Accepted => 'Accepté',
            self::Rejected => 'Refusé',
            self::Closed => 'Classé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::UnderStudy => 'info',
            self::QuoteSent => 'primary',
            self::Accepted => 'success',
            self::Rejected => 'danger',
            self::Closed => 'gray',
        };
    }

    /**
     * Statut initial attribué à toute demande entrante.
     */
    public static function initial(): self
    {
        return self::New;
    }

    /**
     * Les statuts qui comptent comme demande encore à traiter.
     *
     * @return list<string>
     */
    public static function pendingValues(): array
    {
        return [
            self::New->value,
            self::UnderStudy->value,
        ];
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
