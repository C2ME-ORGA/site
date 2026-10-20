<?php

namespace App\Filament\Resources\Emails\Pages;

use App\Filament\Resources\Emails\Actions\ComposeEmailAction;
use App\Filament\Resources\Emails\EmailMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailMessages extends ListRecords
{
    protected static string $resource = EmailMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ComposeEmailAction::make(),
        ];
    }
}
