<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $crm_activity_id
 * @property string|null $client_uuid
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property CarbonImmutable|null $created_at
 */
class CrmActivityAttachment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'crm_activity_id',
        'client_uuid',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected static function booted(): void
    {
        static::deleting(function (CrmActivityAttachment $attachment): void {
            if ($attachment->path !== '') {
                Storage::disk(self::disk())->delete($attachment->path);
            }
        });
    }

    /**
     * @return BelongsTo<CrmActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(CrmActivity::class, 'crm_activity_id');
    }

    /**
     * Disque privé : les photos peuvent montrer des chantiers ou des
     * documents clients et ne doivent jamais être servies publiquement.
     */
    public static function disk(): string
    {
        return (string) config('crm.attachments.disk', 'local');
    }

    public function exists(): bool
    {
        return Storage::disk(self::disk())->exists($this->path);
    }

    /**
     * @return resource|null
     */
    public function readStream()
    {
        return Storage::disk(self::disk())->readStream($this->path);
    }

    /**
     * URL authentifiée de la photo (jamais servie depuis le disque public).
     */
    public function url(): string
    {
        return route('field.attachments.show', $this);
    }

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
     * Métadonnées exposées au mode terrain et au back-office.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'human_size' => $this->humanSize(),
            'url' => $this->url(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
