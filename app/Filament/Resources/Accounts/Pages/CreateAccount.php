<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\Schemas\AccountForm;
use Filament\Resources\Pages\CreateRecord;

abstract class CreateAccount extends CreateRecord
{
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['book'] = static::getResource()::book()->value;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getFormDefaults(): array
    {
        return AccountForm::defaults(static::getResource()::book());
    }
}
