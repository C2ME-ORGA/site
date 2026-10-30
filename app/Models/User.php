<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property string|null $phone
 * @property string|null $job_title
 * @property string|null $email_signature
 * @property bool $is_active
 * @property CarbonImmutable|null $last_login_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role', 'phone', 'job_title', 'email_signature', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Valeurs par défaut appliquées en mémoire, avant même l'écriture en base.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::Commercial->value,
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * L'accès au back-office dépend du rôle et du statut du compte, jamais de
     * l'adresse e-mail : la liste admin.allowed_emails ne sert qu'à amorcer le
     * tout premier administrateur via le seeder.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && ($this->isAdmin() || $this->isCommercial());
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isCommercial(): bool
    {
        return $this->role === UserRole::Commercial;
    }

    /**
     * @return HasMany<CrmAccount, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(CrmAccount::class, 'owner_id');
    }

    /**
     * @return HasMany<CrmOpportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(CrmOpportunity::class, 'owner_id');
    }

    /**
     * @return HasMany<CrmActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class);
    }

    /**
     * @return HasMany<EmailMailbox, $this>
     */
    public function mailboxes(): HasMany
    {
        return $this->hasMany(EmailMailbox::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    /**
     * Canal privé utilisé par Filament pour les notifications temps réel.
     */
    public function receivesBroadcastNotificationsOn(?Notification $notification = null): string
    {
        return str_replace('\\', '.', static::class).'.'.$this->getKey();
    }
}
