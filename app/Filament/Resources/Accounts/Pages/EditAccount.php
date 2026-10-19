<?php

namespace App\Filament\Resources\Accounts\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

abstract class EditAccount extends EditRecord
{
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
