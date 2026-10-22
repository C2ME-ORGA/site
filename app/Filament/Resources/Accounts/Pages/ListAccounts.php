<?php

namespace App\Filament\Resources\Accounts\Pages;

use App\Filament\Resources\Accounts\AccountResource;
use App\Models\CrmOpportunity;
use App\Services\CrmCsvExporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

abstract class ListAccounts extends ListRecords
{
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),

            Action::make('exportAccountsCsv')
                ->label('Exporter les comptes (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    /** @var class-string<AccountResource> $resource */
                    $resource = static::getResource();
                    $book = $resource::book();

                    return app(CrmCsvExporter::class)->accounts(
                        'c2me-comptes-'.$book->value.'-'.now()->format('Y-m-d').'.csv',
                        $this->getTableQueryForExport(),
                        $book,
                    );
                }),

            Action::make('exportOpportunitiesCsv')
                ->label('Exporter les affaires (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (): StreamedResponse {
                    /** @var class-string<AccountResource> $resource */
                    $resource = static::getResource();
                    $book = $resource::book();

                    $accountIds = $this->getTableQueryForExport()
                        ->reorder()
                        ->select('crm_accounts.id');

                    $query = CrmOpportunity::query()
                        ->whereIn('crm_account_id', $accountIds)
                        ->orderByDesc('created_at');

                    return app(CrmCsvExporter::class)->opportunities(
                        'c2me-affaires-'.$book->value.'-'.now()->format('Y-m-d').'.csv',
                        $query,
                    );
                }),
        ];
    }
}
