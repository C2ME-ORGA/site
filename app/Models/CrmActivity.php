<?php

namespace App\Models;

use App\Enums\CrmActivityType;
use Carbon\CarbonImmutable;
use Database\Factories\CrmActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string|null $client_uuid
 * @property int|null $crm_account_id
 * @property int|null $crm_contact_id
 * @property int|null $crm_opportunity_id
 * @property int|null $quote_id
 * @property int|null $user_id
 * @property CrmActivityType $type
 * @property string $subject
 * @property string|null $body
 * @property string|null $outcome
 * @property CarbonImmutable|null $occurred_at
 * @property CarbonImmutable|null $due_at
 * @property CarbonImmutable|null $remind_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $updated_at
 */
class CrmActivity extends Model
{
    /** @use HasFactory<CrmActivityFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_uuid',
        'crm_account_id',
        'crm_contact_id',
        'crm_opportunity_id',
        'quote_id',
        'user_id',
        'type',
        'subject',
        'body',
        'outcome',
        'occurred_at',
        'due_at',
        'remind_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CrmActivityType::class,
            'occurred_at' => 'datetime',
            'due_at' => 'datetime',
            'remind_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CrmActivity $activity): void {
            if ($activity->type->isPlanned() && $activity->occurred_at === null) {
                $activity->occurred_at = null;
            }

            if (! $activity->type->isPlanned() && $activity->occurred_at === null) {
                $activity->occurred_at = now();
            }
        });
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
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isDone()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }

    /**
     * @param  Builder<CrmActivity>  $query
     * @return Builder<CrmActivity>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNotNull('due_at')->whereNull('completed_at');
    }

    /**
     * @param  Builder<CrmActivity>  $query
     * @return Builder<CrmActivity>
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()->where('due_at', '<', now()->startOfDay());
    }

    /**
     * @param  Builder<CrmActivity>  $query
     * @return Builder<CrmActivity>
     */
    public function scopeDueToday(Builder $query): Builder
    {
        return $query->pending()->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]);
    }

    /**
     * @param  Builder<CrmActivity>  $query
     * @return Builder<CrmActivity>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $user->isAdmin()
            ? $query
            : $query->where('user_id', $user->id);
    }
}
