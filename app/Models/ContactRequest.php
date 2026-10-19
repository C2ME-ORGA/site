<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string|null $company
 * @property string $email
 * @property string|null $phone
 * @property string $request_type
 * @property string $message
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $referer
 * @property int|null $crm_account_id
 * @property int|null $crm_contact_id
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $created_at
 */
class ContactRequest extends Model
{
    /**
     * Types de demande proposés au visiteur, dans l'ordre d'affichage.
     *
     * Les pôles de services sont la source de vérité : ajouter un service
     * dans config/site.php le rend automatiquement sélectionnable.
     *
     * @return list<string>
     */
    public static function requestTypes(): array
    {
        $types = array_column(config('site.services'), 'name');

        return [...$types, 'Autre demande'];
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'company',
        'email',
        'phone',
        'request_type',
        'message',
        'ip_address',
        'user_agent',
        'referer',
        'crm_account_id',
        'crm_contact_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<ContactRequest>  $query
     * @return Builder<ContactRequest>
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Demandes déposées avant la date limite de conservation.
     *
     * @param  Builder<ContactRequest>  $query
     * @return Builder<ContactRequest>
     */
    public function scopeExpired(Builder $query, CarbonInterface $before): Builder
    {
        return $query->where('created_at', '<', $before);
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    /**
     * L'adresse de notification est construite via l'option mail.from,
     * mais l'expéditeur du visiteur doit rester exploitable.
     */
    public function replyTo(): string
    {
        return $this->email;
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
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
}
