<?php

namespace App\Filament\Resources\PrescriberAccounts\Pages;

use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\PrescriberAccounts\PrescriberAccountResource;

class ListPrescriberAccounts extends ListAccounts
{
    protected static string $resource = PrescriberAccountResource::class;
}
