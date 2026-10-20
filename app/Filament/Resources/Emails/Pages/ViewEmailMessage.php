<?php

namespace App\Filament\Resources\Emails\Pages;

use App\Filament\Resources\Emails\Actions\ComposeEmailAction;
use App\Filament\Resources\Emails\EmailMessageResource;
use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\EmailMessage;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailMessage extends ViewRecord
{
    protected static string $resource = EmailMessageResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $message = $this->getRecord();

        if ($message instanceof EmailMessage && ! $message->is_read) {
            $message->forceFill(['is_read' => true])->save();
        }
    }

    protected function getHeaderActions(): array
    {
        /** @var EmailMessage $message */
        $message = $this->getRecord();

        $account = $message->account;
        $contact = $message->contact;

        return [
            ComposeEmailAction::make(
                $account instanceof CrmAccount ? $account : null,
                $contact instanceof CrmContact ? $contact : null,
            ),

            ComposeEmailAction::reply($message),

            DeleteAction::make(),
        ];
    }
}
