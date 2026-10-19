<?php

namespace App\Filament\Resources\PrescriberAccounts;

use App\Enums\CrmBook;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\PrescriberAccounts\Pages\CreatePrescriberAccount;
use App\Filament\Resources\PrescriberAccounts\Pages\EditPrescriberAccount;
use App\Filament\Resources\PrescriberAccounts\Pages\ListPrescriberAccounts;
use App\Filament\Resources\PrescriberAccounts\Pages\ViewPrescriberAccount;
use Filament\Resources\Pages\PageRegistration;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PrescriberAccountResource extends AccountResource
{
    protected static ?string $modelLabel = 'prescripteur';

    protected static ?string $pluralModelLabel = 'prescripteurs';

    protected static ?string $navigationLabel = 'Carnet 1 — Prescripteurs';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    public static function book(): CrmBook
    {
        return CrmBook::Prescribers;
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPrescriberAccounts::route('/'),
            'create' => CreatePrescriberAccount::route('/create'),
            'view' => ViewPrescriberAccount::route('/{record}'),
            'edit' => EditPrescriberAccount::route('/{record}/edit'),
        ];
    }
}
