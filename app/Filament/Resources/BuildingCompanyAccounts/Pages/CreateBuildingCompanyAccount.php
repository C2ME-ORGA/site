<?php

namespace App\Filament\Resources\BuildingCompanyAccounts\Pages;

use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\BuildingCompanyAccounts\BuildingCompanyAccountResource;

class CreateBuildingCompanyAccount extends CreateAccount
{
    protected static string $resource = BuildingCompanyAccountResource::class;
}
