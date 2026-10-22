<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Models\CrmAccount;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use TinusG\FilamentCompanyLogoColumn\CompanyLogoColumn;

abstract class ViewAccount extends ViewRecord
{
    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Imprimer la fiche')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn (CrmAccount $record): string => route('crm.print.account', $record))
                ->openUrlInNewTab(),

            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    public function getHeading(): string|Htmlable|null
    {
        $record = $this->getRecord();

        $logoUrl = $record instanceof CrmAccount
            ? CompanyLogoColumn::make('website')->domain($record->website)->size(40)->getLogoUrl()
            : null;

        return view('filament.accounts.heading', [
            'title' => $this->getTitle(),
            'logoUrl' => $logoUrl,
        ]);
    }
}
