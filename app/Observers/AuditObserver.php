<?php

namespace App\Observers;

use App\Enums\AuditEvent;
use App\Models\CrmAccount;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOpportunity;
use App\Models\CrmTag;
use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use App\Models\Quote;
use App\Models\User;
use App\Services\AuditLogger;
use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Journalise les modifications sensibles (statuts, rôles, suppressions…).
 *
 * Les créations ne sont volontairement pas tracées : le journal doit rester
 * lisible et centré sur les changements à valeur d'audit. Les envois d'e-mails
 * sont tracés explicitement par MailboxMailer.
 */
class AuditObserver
{
    /**
     * Modèles audités et attributs surveillés.
     *
     * Une liste vide signifie que seules les suppressions/restaurations du
     * modèle sont journalisées.
     *
     * @var array<class-string<Model>, list<string>>
     */
    public const AUDITED = [
        Quote::class => ['status'],
        CrmOpportunity::class => ['stage', 'probability', 'amount_estimated'],
        CrmAccount::class => ['status'],
        CrmContact::class => [],
        CrmActivity::class => [],
        EmailMessage::class => [],
        EmailMailbox::class => ['is_active', 'user_id'],
        CrmTag::class => [],
        User::class => ['role', 'is_active'],
    ];

    public function __construct(private readonly AuditLogger $logger) {}

    public function updated(Model $model): void
    {
        $watched = self::AUDITED[$model::class] ?? [];

        $old = [];
        $new = [];

        foreach ($watched as $attribute) {
            if (! $model->wasChanged($attribute)) {
                continue;
            }

            $old[$attribute] = self::normalize($model->getOriginal($attribute));
            $new[$attribute] = self::normalize($model->getAttribute($attribute));
        }

        if ($old === [] && $new === []) {
            return;
        }

        // Ne pas journaliser une écriture qui ne change aucune valeur lisible.
        if ($old === $new) {
            return;
        }

        $this->logger->record($model, AuditEvent::Updated, [
            'old' => $old,
            'new' => $new,
        ]);
    }

    public function deleted(Model $model): void
    {
        // Un forceDelete déclenche aussi « deleted » : on évite le doublon.
        if (method_exists($model, 'isForceDeleting') && $model->isForceDeleting()) {
            return;
        }

        $this->logger->record($model, AuditEvent::Deleted);
    }

    public function forceDeleted(Model $model): void
    {
        $this->logger->record($model, AuditEvent::ForceDeleted);
    }

    public function restored(Model $model): void
    {
        $this->logger->record($model, AuditEvent::Restored);
    }

    private static function normalize(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof HasLabel => self::labelToString($value->getLabel()),
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            is_bool($value) => $value ? 'Oui' : 'Non',
            is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }

    private static function labelToString(string|Htmlable|null $label): ?string
    {
        return match (true) {
            $label === null => null,
            is_string($label) => $label,
            default => strip_tags($label->toHtml()),
        };
    }
}
