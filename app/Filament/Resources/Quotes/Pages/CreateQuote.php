<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\Pages\Concerns\UpdatesQuoteTotals;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Quote;
use Filament\Resources\Pages\CreateRecord;

class CreateQuote extends CreateRecord
{
    use UpdatesQuoteTotals;

    protected static string $resource = QuoteResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->withQuoteTotals($data);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Quote) {
            // Les lignes du répéteur sont enregistrées après le devis : ce
            // passage garantit des totaux alignés sur la base (sans écriture
            // si les observateurs les ont déjà mis à jour).
            $record->recalculateTotals();
        }
    }
}
