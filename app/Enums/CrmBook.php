<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CrmBook: string implements HasColor, HasLabel
{
    case Prescribers = 'prescripteurs';

    case IndustrialClients = 'clients_industriels';

    case BuildingCompanies = 'entreprises_batiment';

    public function getLabel(): string
    {
        return match ($this) {
            self::Prescribers => 'Carnet 1 — Prescripteurs',
            self::IndustrialClients => 'Carnet 2 — Clients industriels',
            self::BuildingCompanies => 'Carnet 3 — Entreprises du bâtiment',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Prescribers => 'Prescripteurs',
            self::IndustrialClients => 'Clients industriels',
            self::BuildingCompanies => 'Entreprises du bâtiment',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Prescribers => 'info',
            self::IndustrialClients => 'warning',
            self::BuildingCompanies => 'success',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Prescribers => 'heroicon-o-pencil-square',
            self::IndustrialClients => 'heroicon-o-building-office-2',
            self::BuildingCompanies => 'heroicon-o-wrench-screwdriver',
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

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $book) {
            $options[$book->value] = $book->shortLabel();
        }

        return $options;
    }
}
