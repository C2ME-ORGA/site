<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CrmCategory: string implements HasLabel
{
    case Architects = 'architectes';

    case EngineeringOffices = 'bet';

    case ProjectManagers = 'maitres_oeuvre';

    case QuantitySurveyors = 'economistes';

    case Factories = 'usines';

    case IndustrialMaintenance = 'maintenance_industrielle';

    case FoodIndustry = 'agroalimentaire';

    case Logistics = 'logistique';

    case Builders = 'constructeurs';

    case RoofersCladders = 'couvreurs_bardeurs';

    case GeneralContractors = 'entreprises_generales';

    case Farmers = 'agriculteurs';

    public function getLabel(): string
    {
        return match ($this) {
            self::Architects => 'Architectes',
            self::EngineeringOffices => 'BET',
            self::ProjectManagers => 'Maîtres d’œuvre',
            self::QuantitySurveyors => 'Économistes',
            self::Factories => 'Usines',
            self::IndustrialMaintenance => 'Maintenance industrielle',
            self::FoodIndustry => 'Agroalimentaire',
            self::Logistics => 'Logistique',
            self::Builders => 'Constructeurs',
            self::RoofersCladders => 'Couvreurs-bardeurs',
            self::GeneralContractors => 'Entreprises générales',
            self::Farmers => 'Agriculteurs et bâtiments agricoles',
        };
    }

    /**
     * Carnet auquel la catégorie appartient.
     */
    public function book(): CrmBook
    {
        return match ($this) {
            self::Architects,
            self::EngineeringOffices,
            self::ProjectManagers,
            self::QuantitySurveyors => CrmBook::Prescribers,

            self::Factories,
            self::IndustrialMaintenance,
            self::FoodIndustry,
            self::Logistics => CrmBook::IndustrialClients,

            self::Builders,
            self::RoofersCladders,
            self::GeneralContractors,
            self::Farmers => CrmBook::BuildingCompanies,
        };
    }

    /**
     * Catégories rattachées à un carnet, dans l'ordre d'affichage.
     *
     * @return list<self>
     */
    public static function forBook(CrmBook $book): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $category): bool => $category->book() === $book,
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function optionsForBook(CrmBook $book): array
    {
        $options = [];

        foreach (self::forBook($book) as $category) {
            $options[$category->value] = $category->getLabel();
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $category) {
            $options[$category->value] = $category->getLabel();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
