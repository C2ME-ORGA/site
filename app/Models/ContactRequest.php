<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
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
}
