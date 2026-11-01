<?php

namespace App\Filament\Resources\SecurityAlerts\Pages;

use App\Filament\Resources\SecurityAlerts\SecurityAlertResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListSecurityAlerts extends ListRecords
{
    protected static string $resource = SecurityAlertResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
