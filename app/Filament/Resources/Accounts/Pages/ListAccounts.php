<?php

namespace App\Filament\Resources\Accounts\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

abstract class ListAccounts extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
