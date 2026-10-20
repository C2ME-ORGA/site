<?php

namespace App\Models;

use App\Enums\AccountPotential;
use App\Enums\AccountStatus;
use App\Enums\CrmBook;
use App\Enums\CrmCategory;
use App\Enums\CrmSource;
use Carbon\CarbonImmutable;
use Database\Factories\CrmAccountFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property CrmBook $book
 * @property CrmCategory $category
 * @property string|null $code
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $siret
 * @property string|null $vat_number
 * @property string|null $website
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $mobile
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $postal_code
 * @property string|null $city
 * @property string|null $country
 * @property AccountStatus $status
 * @property CrmSource|null $source
 * @property AccountPotential|null $potential
 * @property int|null $owner_id
 * @property CarbonImmutable|null $last_contacted_at
 * @property CarbonImmutable|null $next_follow_up_at
 * @property array<string, mixed>|null $metadata
 * @property string|null $notes
 * @property CarbonImmutable|null $updated_at
 */
class CrmAccount extends Model
{
    /** @use HasFactory<CrmAccountFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'book',
        'category',
        'code',
        'name',
        'legal_name',
        'siret',
        'vat_number',
        'website',
        'email',
        'phone',
        'mobile',
        'address_line1',
        'address_line2',
        'postal_code',
        'city',
        'country',
        'status',
        'source',
        'potential',
        'owner_id',
        'last_contacted_at',
        'next_follow_up_at',
        'metadata',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'book' => CrmBook::class,
            'category' => CrmCategory::class,
            'status' => AccountStatus::class,
            'source' => CrmSource::class,
            'potential' => AccountPotential::class,
            'metadata' => 'array',
            'last_contacted_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CrmAccount $account): void {
            $account->status ??= AccountStatus::Prospect;
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<CrmContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(CrmContact::class);
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

    /**
     * @return MorphToMany<CrmTag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(CrmTag::class, 'taggable', 'crm_taggables');
    }

    /**
     * @return HasMany<QuoteRequest, $this>
     */
    public function quoteRequests(): HasMany
    {
        return $this->hasMany(QuoteRequest::class);
    }

    public function primaryContact(): ?CrmContact
    {
        return $this->contacts->firstWhere('is_primary', true)
            ?? $this->contacts->first();
    }

    /**
     * @param  Builder<CrmAccount>  $query
     * @return Builder<CrmAccount>
     */
    public function scopeForBook(Builder $query, CrmBook $book): Builder
    {
        return $query->where('book', $book->value);
    }

    /**
     * @param  Builder<CrmAccount>  $query
     * @return Builder<CrmAccount>
     */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $user->isAdmin()
            ? $query
            : $query->where('owner_id', $user->id);
    }

    /**
     * Comptes dont la relance est due ou dépassée.
     *
     * @param  Builder<CrmAccount>  $query
     * @return Builder<CrmAccount>
     */
    public function scopeFollowUpDue(Builder $query): Builder
    {
        return $query->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', now()->endOfDay());
    }

    public function fullAddress(): string
    {
        return collect([
            $this->address_line1,
            $this->address_line2,
            trim(($this->postal_code ?? '').' '.($this->city ?? '')),
            $this->country,
        ])->filter()->implode(', ');
    }
}
