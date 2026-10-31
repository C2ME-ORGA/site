<?php

namespace App\Filament\Resources\Accounts\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use MortalKiller\FilamentPageHeader\Concerns\HasPageHeader;

abstract class EditAccount extends EditRecord
{
    use HasPageHeader;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
