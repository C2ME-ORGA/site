<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use MortalKiller\FilamentPageHeader\Concerns\HasPageHeader;

class EditActivity extends EditRecord
{
    use HasPageHeader;

    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
