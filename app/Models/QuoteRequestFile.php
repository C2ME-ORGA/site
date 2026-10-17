<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class QuoteRequestFile extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'quote_request_id',
        'original_name',
        'path',
        'mime_type',
        'size',
    ];

    /**
     * @return BelongsTo<QuoteRequest, $this>
     */
    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    /**
     * Disque privé : les plans peuvent contenir des données clients et
     * ne doivent jamais être servis depuis un emplacement public.
     */
    public static function disk(): string
    {
        return (string) config('site.quote.storage_disk');
    }

    public function exists(): bool
    {
        return Storage::disk(self::disk())->exists($this->path);
    }

    /**
     * @return resource
     */
    public function readStream()
    {
        return Storage::disk(self::disk())->readStream($this->path);
    }

    /**
     * Supprime le fichier du disque. L'effacement du disque est
     * indépendant de la suppression de l'enregistrement.
     */
    public function purge(): void
    {
        Storage::disk(self::disk())->delete($this->path);

        $this->delete();
    }

    /**
     * Poids exprimé dans une unité lisible.
     */
    public function humanSize(): string
    {
        $bytes = (float) $this->size;

        foreach (['o', 'Ko', 'Mo', 'Go'] as $unit) {
            if ($bytes < 1024 || $unit === 'Go') {
                return round($bytes, $unit === 'o' ? 0 : 1).' '.$unit;
            }

            $bytes /= 1024;
        }

        return round($bytes).' o';
    }

    /**
     * Extension du fichier d'origine, en minuscules.
     */
    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }
}
