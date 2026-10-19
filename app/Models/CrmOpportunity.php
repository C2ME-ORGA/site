<?php

namespace App\Models;

use App\Enums\OpportunityStage;
use Carbon\CarbonImmutable;
use Database\Factories\CrmOpportunityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $reference
 * @property OpportunityStage $stage
 * @property string|null $amount_estimated
 * @property int|null $probability
 * @property CarbonImmutable|null $expected_close_date
 * @property CarbonImmutable|null $won_at
 * @property CarbonImmutable|null $closed_at
 */
class CrmOpportunity extends Model
{
    /** @use HasFactory<CrmOpportunityFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'crm_account_id',
        'crm_contact_id',
        'quote_request_id',
        'title',
        'description',
        'stage',
        'amount_estimated',
        'probability',
        'expected_close_date',
        'source',
        'owner_id',
        'lost_reason',
        'won_at',
        'closed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => OpportunityStage::class,
            'amount_estimated' => 'decimal:2',
            'expected_close_date' => 'date',
            'won_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CrmOpportunity $opportunity): void {
            $opportunity->reference ??= self::nextReference();
            $opportunity->stage ??= OpportunityStage::New;
            $opportunity->probability ??= $opportunity->stage->defaultProbability();
        });
    }

    /**
     * Référence séquentielle annuelle : AFF-2026-00042.
     */
    public static function nextReference(): string
    {
        $year = now()->format('Y');
        $prefix = 'AFF-'.$year.'-';

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
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
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
     * @param  Builder<CrmOpportunity>  $query
     * @return Builder<CrmOpportunity>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('stage', [
            OpportunityStage::Won->value,
            OpportunityStage::Lost->value,
            OpportunityStage::Closed->value,
        ]);
    }

    /**
     * @param  Builder<CrmOpportunity>  $query
     * @return Builder<CrmOpportunity>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $user->isAdmin()
            ? $query
            : $query->where('owner_id', $user->id);
    }

    /**
     * Aligne l'étape sur le statut du dernier devis.
     */
    public function syncFromQuote(Quote $quote): void
    {
        $stage = $quote->status->opportunityStage();

        if ($stage === null) {
            return;
        }

        $this->stage = $stage;
        $this->probability = $stage->defaultProbability();

        if ($stage === OpportunityStage::Won) {
            $this->won_at ??= now();
        }

        if ($stage->isClosed()) {
            $this->closed_at ??= now();
        }

        $this->save();
    }

    public function isOpen(): bool
    {
        return $this->stage->isOpen();
    }
}
