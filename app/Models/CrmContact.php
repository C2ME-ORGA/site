<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CrmContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $crm_account_id
 * @property string|null $civility
 * @property string $first_name
 * @property string $last_name
 * @property string|null $job_title
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile
 * @property bool $is_primary
 * @property bool $decision_maker
 * @property bool $do_not_contact
 * @property string|null $notes
 * @property CarbonImmutable|null $last_contacted_at
 * @property CarbonImmutable|null $updated_at
 */
class CrmContact extends Model
{
    /** @use HasFactory<CrmContactFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'crm_account_id',
        'civility',
        'first_name',
        'last_name',
        'job_title',
        'email',
        'phone',
        'mobile',
        'is_primary',
        'decision_maker',
        'do_not_contact',
        'notes',
        'last_contacted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'decision_maker' => 'boolean',
            'do_not_contact' => 'boolean',
            'last_contacted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CrmAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'crm_account_id');
    }

    /**
     * @return HasMany<CrmOpportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class);
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

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * @param  Builder<CrmContact>  $query
     * @return Builder<CrmContact>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term): void {
            $like = '%'.$term.'%';

            $query->where('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }
}
