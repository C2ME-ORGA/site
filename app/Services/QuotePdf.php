<?php

namespace App\Services;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class QuotePdf
{
    /**
     * Génère le PDF du devis et l'enregistre sur le disque privé.
     */
    public function generate(Quote $quote): string
    {
        $quote->loadMissing(['account', 'contact', 'lines', 'owner']);

        $pdf = Pdf::loadView('pdf.quote', [
            'quote' => $quote,
            'company' => config('site.company'),
            'legal' => config('site.legal.editor'),
        ])->setPaper('a4');

        $path = trim((string) config('crm.quotes.storage_directory'), '/')
            .'/'.$quote->reference.'.pdf';

        Storage::disk((string) config('crm.quotes.storage_disk'))->put($path, $pdf->output());

        $quote->forceFill(['pdf_path' => $path])->saveQuietly();

        return $path;
    }

    public function exists(Quote $quote): bool
    {
        return $quote->pdf_path !== null
            && Storage::disk((string) config('crm.quotes.storage_disk'))->exists($quote->pdf_path);
    }

    /**
     * @return resource|null
     */
    public function readStream(Quote $quote)
    {
        if (! $this->exists($quote)) {
            return null;
        }

        return Storage::disk((string) config('crm.quotes.storage_disk'))
            ->readStream((string) $quote->pdf_path);
    }

    public function delete(Quote $quote): void
    {
        if ($this->exists($quote)) {
            Storage::disk((string) config('crm.quotes.storage_disk'))->delete((string) $quote->pdf_path);
        }
    }
}
