<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string|null $description
 * @property string|null $location
 * @property int|null $crm_account_id
 * @property int|null $crm_contact_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property bool $all_day
 * @property int|null $reminder_minutes
 * @property string|null $recurrence
 * @property CarbonImmutable|null $recurrence_until
 * @property bool $share_with_all
 * @property int|null $shared_users_count
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CalendarEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'location',
        'crm_account_id',
        'crm_contact_id',
        'starts_at',
        'ends_at',
        'all_day',
        'reminder_minutes',
        'recurrence',
        'recurrence_until',
        'share_with_all',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
            'recurrence_until' => 'date',
            'share_with_all' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
     * @return BelongsToMany<User, $this>
     */
    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Événements du propriétaire, partagés à tous ou explicitement à
     * l'utilisateur. Les événements privés des autres restent invisibles,
     * y compris pour un administrateur.
     *
     * @param  Builder<CalendarEvent>  $query
     * @return Builder<CalendarEvent>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->where('user_id', $user->id)
            ->orWhere('share_with_all', true)
            ->orWhereHas(
                'sharedUsers',
                fn (Builder $query) => $query->where('users.id', $user->id),
            ));
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function isShared(): bool
    {
        return $this->share_with_all || ($this->shared_users_count ?? $this->sharedUsers()->count()) > 0;
    }

    /**
     * Occurrences de l'événement dans la fenêtre demandée, récurrence simple
     * incluse (hebdomadaire ou mensuelle, sans exception).
     *
     * @return list<array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function occurrencesBetween(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $base = $this->starts_at->toImmutable();
        $end = $this->ends_at?->toImmutable();
        $durationDays = 0;
        $durationSeconds = 3600;
        $until = $this->recurrence_until?->toImmutable()->endOfDay();

        if ($this->all_day) {
            $base = $base->startOfDay();
            $end = ($end ?? $base)->startOfDay();
            $durationDays = max(0, (int) $base->diffInDays($end));
        } else {
            $durationSeconds = $end !== null ? max(60, (int) $base->diffInSeconds($end)) : 3600;
        }

        $occurrences = [];
        $step = 0;

        // Garde-fou : une récurrence sans date de fin combinée à une fenêtre
        // très large pourrait produire un nombre illimité d'occurrences.
        // 500 occurrences couvrent environ 9,5 ans en hebdomadaire et 41 ans
        // en mensuel ; la limite reste ajustable via la configuration.
        $limit = max(1, (int) config('crm.calendar.max_occurrences', 500));

        while ($step < $limit) {
            $start = match ($this->recurrence) {
                'weekly' => $base->addWeeks($step),
                'monthly' => $base->addMonthsNoOverflow($step),
                default => $base,
            };

            if ($step > 0 && $until !== null && $start->greaterThan($until)) {
                break;
            }

            if ($start->greaterThan($to)) {
                break;
            }

            $occurrenceEnd = $this->all_day
                ? $start->addDays($durationDays)->endOfDay()
                : $start->addSeconds($durationSeconds);

            if ($occurrenceEnd->greaterThanOrEqualTo($from)) {
                $occurrences[] = ['start' => $start, 'end' => $occurrenceEnd];
            }

            if ($this->recurrence === null) {
                break;
            }

            $step++;
        }

        return $occurrences;
    }
}
