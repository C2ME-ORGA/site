<?php

namespace App\Filament\Resources\Emails\Pages;

use App\Filament\Resources\Emails\Actions\ComposeEmailAction;
use App\Filament\Resources\Emails\EmailMessageResource;
use App\Models\CrmAccount;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\EmailMessage;
use App\Models\Quote;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;
use MortalKiller\FilamentPageHeader\Concerns\HasPageHeader;

class ViewEmailMessage extends ViewRecord
{
    use HasPageHeader;

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

            Action::make('classify')
                ->label('Classer')
                ->icon('heroicon-o-tag')
                ->color('gray')
                ->fillForm(fn (): array => [
                    'crm_account_id' => $message->crm_account_id,
                    'crm_contact_id' => $message->crm_contact_id,
                    'crm_opportunity_id' => $message->crm_opportunity_id,
                    'quote_id' => $message->quote_id,
                ])
                ->schema([
                    Select::make('crm_account_id')
                        ->label('Compte')
                        ->options(fn (): array => CrmAccount::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),

                    Select::make('crm_contact_id')
                        ->label('Contact')
                        ->options(fn (): array => CrmContact::query()->orderBy('last_name')->orderBy('first_name')->get()
                            ->mapWithKeys(fn (CrmContact $contact): array => [$contact->id => $contact->fullName()])
                            ->all())
                        ->searchable(),

                    Select::make('crm_opportunity_id')
                        ->label('Affaire')
                        ->options(fn (): array => CrmOpportunity::query()->orderBy('title')->pluck('title', 'id')->all())
                        ->searchable(),

                    Select::make('quote_id')
                        ->label('Devis')
                        ->options(fn (): array => Quote::query()->orderByDesc('issued_at')->pluck('reference', 'id')->all())
                        ->searchable(),
                ])
                ->action(function (array $data) use ($message): void {
                    $message->update([
                        'crm_account_id' => $data['crm_account_id'] ?? null,
                        'crm_contact_id' => $data['crm_contact_id'] ?? null,
                        'crm_opportunity_id' => $data['crm_opportunity_id'] ?? null,
                        'quote_id' => $data['quote_id'] ?? null,
                    ]);
                }),

            DeleteAction::make(),
        ];
    }
}
