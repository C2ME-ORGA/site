<?php

namespace App\Models;

use App\Enums\AuditEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Entrée du journal d'audit des modifications sensibles.
 *
 * @property int $id
 * @property int|null $user_id
 * @property AuditEvent $event
 * @property string $auditable_type
 * @property int $auditable_id
 * @property string|null $auditable_label
 * @property array{old?: array<string, string|null>, new?: array<string, string|null>}|null $changes
 * @property string|null $ip_address
 * @property CarbonImmutable|null $created_at
 */
class AuditLog extends Model
{
    /**
     * Journal en ajout seul : jamais de mise à jour.
     */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'changes',
        'ip_address',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    public function changeList(): array
    {
        $changes = $this->changesArray();
        $old = $changes['old'] ?? [];
        $new = $changes['new'] ?? [];

        $list = [];

        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            $list[$field] = sprintf(
                '%s → %s',
                $old[$field] ?? '—',
                $new[$field] ?? '—',
            );
        }

        return $list;
    }

    public function oldValue(string $field): ?string
    {
        return $this->changesArray()['old'][$field] ?? null;
    }

    public function newValue(string $field): ?string
    {
        return $this->changesArray()['new'][$field] ?? null;
    }

    /**
     * @return array{old?: array<string, string|null>, new?: array<string, string|null>}
     */
    private function changesArray(): array
    {
        $changes = $this->getAttribute('changes');

        return is_array($changes) ? $changes : [];
    }
}
