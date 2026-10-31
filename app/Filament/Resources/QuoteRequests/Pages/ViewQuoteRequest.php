<?php

namespace App\Filament\Resources\QuoteRequests\Pages;

use App\Filament\Resources\QuoteRequests\Actions\QuoteRequestActions;
use App\Filament\Resources\QuoteRequests\QuoteRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use MortalKiller\FilamentPageHeader\Concerns\HasPageHeader;

class ViewQuoteRequest extends ViewRecord
{
    use HasPageHeader;

    protected static string $resource = QuoteRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            QuoteRequestActions::reply(),
            QuoteRequestActions::downloadPlans(),
            QuoteRequestActions::resolve(),
            QuoteRequestActions::markAsQuoted(),
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
