<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AuditEvent: string implements HasColor, HasLabel
{
    case Updated = 'updated';

    case Deleted = 'deleted';

    case ForceDeleted = 'force_deleted';

    case Restored = 'restored';

    case Sent = 'sent';

    case Anonymized = 'anonymized';

    case Exported = 'exported';

    public function getLabel(): string
    {
        return match ($this) {
            self::Updated => 'Modification',
            self::Deleted => 'Suppression',
            self::ForceDeleted => 'Suppression définitive',
            self::Restored => 'Restauration',
            self::Sent => 'Envoi',
            self::Anonymized => 'Anonymisation',
            self::Exported => 'Export RGPD',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Updated => 'warning',
            self::Deleted => 'danger',
            self::ForceDeleted => 'danger',
            self::Restored => 'success',
            self::Sent => 'info',
            self::Anonymized => 'danger',
            self::Exported => 'info',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Updated => 'heroicon-o-pencil-square',
            self::Deleted => 'heroicon-o-trash',
            self::ForceDeleted => 'heroicon-o-fire',
            self::Restored => 'heroicon-o-arrow-uturn-left',
            self::Sent => 'heroicon-o-paper-airplane',
            self::Anonymized => 'heroicon-o-user-minus',
            self::Exported => 'heroicon-o-arrow-down-tray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $event) {
            $options[$event->value] = $event->getLabel();
        }

        return $options;
    }
}
