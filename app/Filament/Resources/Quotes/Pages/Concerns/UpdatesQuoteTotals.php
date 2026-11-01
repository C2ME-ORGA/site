<?php

namespace App\Filament\Resources\Quotes\Pages\Concerns;

use App\Models\Quote;
use App\Models\QuoteLine;

/**
 * Recalcule les totaux du devis à partir des lignes du formulaire, en direct
 * (ajout, modification ou suppression) et juste avant l'enregistrement.
 *
 * Le calcul est partagé avec QuoteLineObserver et Quote::recalculateTotals()
 * pour que le formulaire et la base de données appliquent exactement les mêmes
 * règles. Les montants soumis par le client ne sont jamais conservés : ils sont
 * toujours recalculés à partir des lignes.
 */
trait UpdatesQuoteTotals
{
    /**
     * Champs dont la modification déclenche le recalcul : les lignes
     * (quantité, prix, remise) et le taux de TVA.
     */
    public function updated(string $property): void
    {
        if ($property !== 'data.vat_rate' && ! str_starts_with($property, 'data.lines')) {
            return;
        }

        $this->data = $this->withQuoteTotals($this->data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withQuoteTotals(array $data): array
    {
        $lines = $data['lines'] ?? $this->data['lines'] ?? [];
        $lines = is_array($lines) ? $lines : [];

        $totalHt = 0.0;

        foreach ($lines as $key => $line) {
            $line = is_array($line) ? $line : [];

            $lineTotal = QuoteLine::calculateTotalHt(
                $line['quantity'] ?? null,
                $line['unit_price'] ?? null,
                $line['discount_percent'] ?? null,
            );

            $data['lines'][$key]['total_ht'] = $lineTotal;
            $totalHt += $lineTotal;
        }

        $totalHt = round($totalHt, 2);
        $vatRate = (float) ($data['vat_rate'] ?? $this->data['vat_rate'] ?? 0);

        $data['vat_rate'] = $vatRate;
        $data['amount_ht'] = $totalHt;
        $data['amount_ttc'] = Quote::calculateAmountTtc($totalHt, $vatRate);

        return $data;
    }
}
