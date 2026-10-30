<?php

namespace App\Filament\Resources\ContactRequests\Pages;

use App\Filament\Resources\ContactRequests\Actions\ContactRequestActions;
use App\Filament\Resources\ContactRequests\ContactRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewContactRequest extends ViewRecord
{
    protected static string $resource = ContactRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContactRequestActions::reply(),
            ContactRequestActions::resolve(),
            ContactRequestActions::markAsRead(),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
