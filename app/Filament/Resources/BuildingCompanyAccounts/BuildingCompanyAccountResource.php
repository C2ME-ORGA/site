<?php

namespace App\Filament\Resources\BuildingCompanyAccounts;

use App\Enums\CrmBook;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\BuildingCompanyAccounts\Pages\CreateBuildingCompanyAccount;
use App\Filament\Resources\BuildingCompanyAccounts\Pages\EditBuildingCompanyAccount;
use App\Filament\Resources\BuildingCompanyAccounts\Pages\ListBuildingCompanyAccounts;
use App\Filament\Resources\BuildingCompanyAccounts\Pages\ViewBuildingCompanyAccount;
use Filament\Resources\Pages\PageRegistration;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class BuildingCompanyAccountResource extends AccountResource
{
    protected static ?string $modelLabel = 'entreprise du bâtiment';

    protected static ?string $pluralModelLabel = 'entreprises du bâtiment';

    protected static ?string $navigationLabel = 'Carnet 3 — Entreprises du bâtiment';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 12;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    public static function book(): CrmBook
    {
        return CrmBook::BuildingCompanies;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListBuildingCompanyAccounts::route('/'),
            'create' => CreateBuildingCompanyAccount::route('/create'),
            'view' => ViewBuildingCompanyAccount::route('/{record}'),
            'edit' => EditBuildingCompanyAccount::route('/{record}/edit'),
        ];
    }
}
