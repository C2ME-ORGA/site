<?php

namespace App\Filament\Resources\PrescriberAccounts\Pages;

use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\PrescriberAccounts\PrescriberAccountResource;

class CreatePrescriberAccount extends CreateAccount
{
    protected static string $resource = PrescriberAccountResource::class;
}
