<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\Actions\QuoteTransitions;
use App\Filament\Resources\Quotes\Pages\Concerns\UpdatesQuoteTotals;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use App\Services\QuotePdf;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;
use MortalKiller\FilamentPageHeader\Concerns\HasPageHeader;
use RuntimeException;

class EditQuote extends EditRecord
{
    use HasPageHeader;
    use UpdatesQuoteTotals;

    protected static string $resource = QuoteResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->withQuoteTotals($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pdf')
                ->label('Générer le PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function (): mixed {
                    $quote = $this->quote();

                    app(QuotePdf::class)->generate($quote);

                    return Storage::disk((string) config('crm.quotes.storage_disk'))
                        ->download((string) $quote->pdf_path);
                }),

            ...QuoteTransitions::make(),

            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->quote()->recalculateTotals();
    }

    private function quote(): Quote
    {
        $record = $this->getRecord();

        if (! $record instanceof Quote) {
            throw new RuntimeException('Le devis attendu est introuvable.');
        }

        return $record;
    }
}
