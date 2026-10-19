<?php

namespace App\Models;

use App\Enums\QuoteKind;
use App\Enums\QuoteStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * @property int $id
 * @property QuoteKind $kind
 * @property QuoteStatus $status
 * @property string $name
 * @property string|null $company
 * @property string $email
 * @property string|null $phone
 * @property string|null $city
 * @property string|null $message
 * @property array<string, mixed>|null $payload
 * @property string|null $note
 * @property int|null $owner_id
 * @property int|null $crm_account_id
 * @property int|null $crm_contact_id
 * @property int|null $crm_opportunity_id
 * @property CarbonImmutable|null $quoted_at
 * @property CarbonImmutable|null $created_at
 */
class QuoteRequest extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'status',
        'name',
        'company',
        'email',
        'phone',
        'city',
        'message',
        'payload',
        'note',
        'quoted_at',
        'ip_address',
        'user_agent',
        'referer',
        'crm_account_id',
        'crm_contact_id',
        'crm_opportunity_id',
        'owner_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => QuoteKind::class,
            'status' => QuoteStatus::class,
            'payload' => 'array',
            'quoted_at' => 'datetime',
        ];
    }

    /**
     * Toute demande entrante démarre au statut « nouveau », sans que le
     * code appelant ait à le préciser.
     */
    protected static function booted(): void
    {
        static::creating(function (QuoteRequest $quote): void {
            $quote->status ??= QuoteStatus::initial();
        });
    }

    /**
     * @return HasMany<QuoteRequestFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(QuoteRequestFile::class);
    }

    /**
     * @return BelongsTo<CrmAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'crm_account_id');
    }

    /**
     * @return BelongsTo<CrmContact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(CrmContact::class, 'crm_contact_id');
    }

    /**
     * @return BelongsTo<CrmOpportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'crm_opportunity_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Demandes encore à traiter par le bureau d'études.
     *
     * @param  Builder<QuoteRequest>  $query
     * @return Builder<QuoteRequest>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', QuoteStatus::pendingValues());
    }

    /**
     * Demandes déposées avant la date limite de conservation.
     *
     * @param  Builder<QuoteRequest>  $query
     * @return Builder<QuoteRequest>
     */
    public function scopeExpired(Builder $query, CarbonInterface $before): Builder
    {
        return $query->where('created_at', '<', $before);
    }

    public function isPending(): bool
    {
        return in_array($this->status->value, QuoteStatus::pendingValues(), true);
    }

    /**
     * Passe la demande au statut « devis envoyé », une seule fois.
     */
    public function markAsQuoted(): void
    {
        $this->forceFill([
            'status' => QuoteStatus::QuoteSent,
            'quoted_at' => $this->quoted_at ?? now(),
        ])->save();
    }

    /**
     * L'adresse de notification est construite via l'option mail.from,
     * mais l'expéditeur du visiteur doit rester exploitable.
     */
    public function replyTo(): string
    {
        return $this->email;
    }

    /**
     * Résumé lisible de la charge utile, pour l'e-mail de notification
     * et l'affichage dans le back-office.
     *
     * @return array<string, string>
     */
    public function summary(): array
    {
        $rows = [];

        foreach ($this->payload ?? [] as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $rows[$key] = is_array($value)
                ? implode(' · ', array_map(strval(...), $value))
                : (string) $value;
        }

        return $rows;
    }

    /**
     * Regroupe les plans joints dans une archive et la renvoie au
     * navigateur. Les fichiers restent sur le disque privé.
     */
    public function downloadArchive(): StreamedResponse
    {
        $files = $this->files->filter->exists()->values();

        return response()->streamDownload(function () use ($files): void {
            $archive = tempnam(sys_get_temp_dir(), 'c2me-plans');
            $zip = new ZipArchive;

            $zip->open($archive, ZipArchive::OVERWRITE);

            foreach ($files as $index => $file) {
                $stream = $file->readStream();

                if ($stream === null) {
                    continue;
                }

                // Deux plans peuvent porter le même nom : on préfixe pour
                // éviter qu'un fichier n'écrase l'autre dans l'archive.
                $zip->addFromString(
                    str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'-'.$file->original_name,
                    stream_get_contents($stream) ?: ''
                );

                fclose($stream);
            }

            $zip->close();

            readfile($archive);
            unlink($archive);
        }, $this->archiveName(), [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Nom attribué à l'archive téléchargée.
     */
    public function archiveName(): string
    {
        return sprintf(
            'plans-%s-%d.zip',
            Str::slug($this->name) ?: 'c2me',
            $this->id
        );
    }
}
