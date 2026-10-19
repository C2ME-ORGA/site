<?php

namespace App\Filament\Resources\IndustrialAccounts\Pages;

use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\IndustrialAccounts\IndustrialAccountResource;

class ListIndustrialAccounts extends ListAccounts
{
    protected static string $resource = IndustrialAccountResource::class;
}
