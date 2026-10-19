<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CrmActivityType: string implements HasColor, HasLabel
{
    case Call = 'appel';

    case Email = 'email';

    case Meeting = 'rdv';

    case Visit = 'visite';

    case FollowUp = 'relance';

    case Note = 'note';

    case Task = 'tache';

    public function getLabel(): string
    {
        return match ($this) {
            self::Call => 'Appel',
            self::Email => 'E-mail',
            self::Meeting => 'Rendez-vous',
            self::Visit => 'Visite',
            self::FollowUp => 'Relance',
            self::Note => 'Note',
            self::Task => 'Tâche',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Call => 'info',
            self::Email => 'primary',
            self::Meeting => 'success',
            self::Visit => 'success',
            self::FollowUp => 'warning',
            self::Note => 'gray',
            self::Task => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Call => 'heroicon-o-phone',
            self::Email => 'heroicon-o-envelope',
            self::Meeting => 'heroicon-o-calendar-days',
            self::Visit => 'heroicon-o-map-pin',
            self::FollowUp => 'heroicon-o-arrow-path',
            self::Note => 'heroicon-o-document-text',
            self::Task => 'heroicon-o-check-circle',
        };
    }

    /**
     * Une tâche ou une relance attend une action ; les autres types
     * décrivent une interaction déjà réalisée.
     */
    public function isPlanned(): bool
    {
        return in_array($this, [self::FollowUp, self::Task], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $type) {
            $options[$type->value] = $type->getLabel();
        }

        return $options;
    }
}
