<?php

namespace App\Models;

use App\Enums\QuoteDocumentStatus;
use App\Observers\QuoteObserver;
use Carbon\CarbonImmutable;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $reference
 * @property QuoteDocumentStatus $status
 * @property string|null $pdf_path
 * @property string|float|null $amount_ht
 * @property string|float|null $vat_rate
 * @property string|float|null $amount_ttc
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $valid_until
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $refused_at
 * @property int|null $owner_id
 */
#[ObservedBy([QuoteObserver::class])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'version',
        'crm_opportunity_id',
        'crm_account_id',
        'crm_contact_id',
        'quote_request_id',
        'status',
        'subject',
        'notes',
        'issued_at',
        'valid_until',
        'amount_ht',
        'vat_rate',
        'amount_ttc',
        'pdf_path',
        'sent_at',
        'accepted_at',
        'refused_at',
        'owner_id',
    ];

    /**
     * Valeurs par défaut pour qu'un devis créé sans ligne soit inséré avec
     * des totaux à zéro (les montants sont NOT NULL en base).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'amount_ht' => 0,
        'vat_rate' => 0,
        'amount_ttc' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteDocumentStatus::class,
            'version' => 'integer',
            'issued_at' => 'date',
            'valid_until' => 'date',
            'amount_ht' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'amount_ttc' => 'decimal:2',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'refused_at' => 'datetime',
        ];
    }

    /**
     * Référence séquentielle annuelle : DEV-2026-00042.
     */
    public static function nextReference(): string
    {
        $year = now()->format('Y');
        $prefix = 'DEV-'.$year.'-';

        $last = self::withTrashed()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $sequence = $last !== null
            ? ((int) substr($last, strlen($prefix))) + 1
            : 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return BelongsTo<CrmOpportunity, $this>
     */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'crm_opportunity_id');
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
     * @return BelongsTo<QuoteRequest, $this>
     */
    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<QuoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<CrmActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    /**
     * @return HasMany<EmailMessage, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(EmailMessage::class);
    }

    /**
     * Calcule le montant TTC à partir du montant HT et du taux de TVA.
     */
    public static function calculateAmountTtc(mixed $amountHt, mixed $vatRate): float
    {
        $amountHt = is_numeric($amountHt) ? (float) $amountHt : 0.0;
        $vatRate = is_numeric($vatRate) ? (float) $vatRate : 0.0;

        return round($amountHt * (1 + ($vatRate / 100)), 2);
    }

    /**
     * Recalcule les totaux HT et TTC à partir des lignes persistées.
     */
    public function recalculateTotals(): void
    {
        $this->amount_ht = round((float) $this->lines()->sum('total_ht'), 2);
        $this->amount_ttc = self::calculateAmountTtc($this->amount_ht, $this->vat_rate);

        if (! $this->isDirty()) {
            return;
        }

        $this->save();
    }

    /**
     * @param  Builder<Quote>  $query
     * @return Builder<Quote>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $user->isAdmin()
            ? $query
            : $query->where('owner_id', $user->id);
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null
            && $this->status === QuoteDocumentStatus::Sent
            && $this->valid_until->isPast();
    }

    /**
     * Applique une transition de statut si elle est autorisée.
     *
     * @return bool `true` si le statut a été modifié (ou était déjà le bon).
     */
    public function transitionTo(QuoteDocumentStatus $status): bool
    {
        if ($this->status === $status) {
            return true;
        }

        if (! $this->status->canTransitionTo($status)) {
            return false;
        }

        $this->status = $status;

        return $this->save();
    }
}
