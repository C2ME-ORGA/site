<?php

namespace App\Filament\Resources\Emails\Pages;

use App\Filament\Resources\Emails\Actions\ComposeEmailAction;
use App\Filament\Resources\Emails\EmailMessageResource;
use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\EmailMessage;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

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
        $message = $this->getRecord();

        $account = $message instanceof EmailMessage ? $message->account : null;
        $contact = $message instanceof EmailMessage ? $message->contact : null;

        return [
            ComposeEmailAction::make(
                $account instanceof CrmAccount ? $account : null,
                $contact instanceof CrmContact ? $contact : null,
            ),

            Action::make('reply')
                ->label('Répondre')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('info')
                ->url(fn (): string => 'mailto:'.($message instanceof EmailMessage ? $message->from_email : ''))
                ->visible(fn (): bool => $message instanceof EmailMessage && ! $message->is_sent),

            DeleteAction::make(),
        ];
    }
}
