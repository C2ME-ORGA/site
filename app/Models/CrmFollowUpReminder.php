<?php

namespace App\Models;

use App\Enums\FollowUpReminderStage;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $crm_account_id
 * @property int $user_id
 * @property CarbonImmutable $due_at
 * @property FollowUpReminderStage $stage
 * @property CarbonImmutable|null $database_sent_at
 * @property CarbonImmutable|null $mail_sent_at
 * @property int $attempts
 * @property string|null $last_error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class CrmFollowUpReminder extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'crm_account_id',
        'user_id',
        'due_at',
        'stage',
        'database_sent_at',
        'mail_sent_at',
        'attempts',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'stage' => FollowUpReminderStage::class,
            'database_sent_at' => 'datetime',
            'mail_sent_at' => 'datetime',
            'attempts' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<CrmFollowUpReminder>  $query
     * @return Builder<CrmFollowUpReminder>
     */
    public function scopeForStage(Builder $query, FollowUpReminderStage $stage): Builder
    {
        return $query->where('stage', $stage->value);
    }

    /**
     * @param  Builder<CrmFollowUpReminder>  $query
     * @return Builder<CrmFollowUpReminder>
     */
    public function scopePendingDatabase(Builder $query): Builder
    {
        return $query->whereNull('database_sent_at');
    }

    /**
     * @param  Builder<CrmFollowUpReminder>  $query
     * @return Builder<CrmFollowUpReminder>
     */
    public function scopePendingMail(Builder $query): Builder
    {
        return $query->whereNull('mail_sent_at');
    }

    public function needsDatabase(): bool
    {
        return $this->database_sent_at === null;
    }

    public function needsMail(): bool
    {
        return $this->mail_sent_at === null;
    }

    public function isComplete(): bool
    {
        return ! $this->needsDatabase() && ! $this->needsMail();
    }
}
