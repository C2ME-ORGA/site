<?php

namespace App\Filament\Resources\Quotes\Pages\Concerns;

/**
 * Recalcule les totaux du devis à partir des lignes du formulaire, en direct
 * (ajout, modification ou suppression) et juste avant l'enregistrement.
 *
 * La persistance reste garantie par QuoteLineObserver et Quote::recalculateTotals().
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
        $lines = $data['lines'] ?? $this->data['lines'] ?? null;

        if (! is_array($lines)) {
            return $data;
        }

        $totalHt = 0.0;

        foreach ($lines as $key => $line) {
            $lineTotal = self::lineTotalHt(is_array($line) ? $line : []);

            $data['lines'][$key]['total_ht'] = $lineTotal;
            $totalHt += $lineTotal;
        }

        $totalHt = round($totalHt, 2);
        $vatRate = (float) ($data['vat_rate'] ?? 0);

        $data['amount_ht'] = $totalHt;
        $data['amount_ttc'] = round($totalHt * (1 + ($vatRate / 100)), 2);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private static function lineTotalHt(array $line): float
    {
        $gross = (float) ($line['quantity'] ?? 0) * (float) ($line['unit_price'] ?? 0);
        $discount = $gross * ((float) ($line['discount_percent'] ?? 0) / 100);

        return round($gross - $discount, 2);
    }
}
