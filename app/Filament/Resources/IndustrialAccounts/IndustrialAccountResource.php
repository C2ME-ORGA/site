<?php

namespace App\Filament\Resources\IndustrialAccounts;

use App\Enums\CrmBook;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\IndustrialAccounts\Pages\CreateIndustrialAccount;
use App\Filament\Resources\IndustrialAccounts\Pages\EditIndustrialAccount;
use App\Filament\Resources\IndustrialAccounts\Pages\ListIndustrialAccounts;
use App\Filament\Resources\IndustrialAccounts\Pages\ViewIndustrialAccount;
use Filament\Resources\Pages\PageRegistration;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class IndustrialAccountResource extends AccountResource
{
    protected static ?string $modelLabel = 'client industriel';

    protected static ?string $pluralModelLabel = 'clients industriels';

    protected static ?string $navigationLabel = 'Carnet 2 — Clients industriels';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 11;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    public static function book(): CrmBook
    {
        return CrmBook::IndustrialClients;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListIndustrialAccounts::route('/'),
            'create' => CreateIndustrialAccount::route('/create'),
            'view' => ViewIndustrialAccount::route('/{record}'),
            'edit' => EditIndustrialAccount::route('/{record}/edit'),
        ];
    }
}
