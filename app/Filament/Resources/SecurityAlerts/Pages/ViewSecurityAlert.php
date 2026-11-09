<?php

namespace App\Filament\Resources\SecurityAlerts\Pages;

use App\Filament\Resources\SecurityAlerts\SecurityAlertResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewSecurityAlert extends ViewRecord
{
    protected static string $resource = SecurityAlertResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            SecurityAlertResource::acknowledgeAction(),
            SecurityAlertResource::resolveAction(),
        ];
    }
}
