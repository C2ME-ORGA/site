<?php

namespace App\Filament\Resources\Mailboxes\Pages;

use App\Filament\Resources\Mailboxes\MailboxResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use MortalKiller\FilamentPageHeader\Concerns\HasPageHeader;

class EditMailbox extends EditRecord
{
    use HasPageHeader;

    protected static string $resource = MailboxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
