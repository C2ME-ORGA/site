<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OpportunityStage: string implements HasColor, HasLabel
{
    case New = 'nouveau';

    case Qualified = 'qualifie';

    case Study = 'etude';

    case QuoteSent = 'devis_envoye';

    case Negotiation = 'negociation';

    case Won = 'gagne';

    case Lost = 'perdu';

    case Closed = 'classe';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nouveau',
            self::Qualified => 'Qualifié',
            self::Study => 'En étude',
            self::QuoteSent => 'Devis envoyé',
            self::Negotiation => 'Négociation',
            self::Won => 'Gagné',
            self::Lost => 'Perdu',
            self::Closed => 'Classé',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'gray',
            self::Qualified => 'info',
            self::Study => 'warning',
            self::QuoteSent => 'primary',
            self::Negotiation => 'warning',
            self::Won => 'success',
            self::Lost => 'danger',
            self::Closed => 'gray',
        };
    }

    /**
     * Probabilité par défaut associée à l'étape.
     */
    public function defaultProbability(): ?int
    {
        return match ($this) {
            self::New => 10,
            self::Qualified => 25,
            self::Study => 40,
            self::QuoteSent => 60,
            self::Negotiation => 75,
            self::Won => 100,
            self::Lost => 0,
            self::Closed => null,
        };
    }

    /**
     * Étapes encore ouvertes, hors gagné, perdu et classé.
     *
     * @return list<self>
     */
    public static function openCases(): array
    {
        return array_filter(
            self::cases(),
            fn (self $stage): bool => ! in_array($stage, [self::Won, self::Lost, self::Closed], true),
        );
    }

    public function isOpen(): bool
    {
        return in_array($this, self::openCases(), true);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Won, self::Lost, self::Closed], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $stage) {
            $options[$stage->value] = $stage->getLabel();
        }

        return $options;
    }
}
