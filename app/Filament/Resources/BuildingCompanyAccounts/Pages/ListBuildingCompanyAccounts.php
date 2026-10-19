<?php

namespace App\Filament\Resources\BuildingCompanyAccounts\Pages;

use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\BuildingCompanyAccounts\BuildingCompanyAccountResource;

class ListBuildingCompanyAccounts extends ListAccounts
{
    protected static string $resource = BuildingCompanyAccountResource::class;
}
