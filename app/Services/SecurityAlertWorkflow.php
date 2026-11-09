<?php

namespace App\Services;

use App\Models\User;
use DomainException;
use MKWebDesign\FilamentWatchdog\Models\SecurityAlert;

/**
 * Transitions autorisées sur une alerte de sécurité.
 *
 * Le paquet expose des méthodes d'acquittement et de résolution, mais ne
 * vérifie aucune transition : une alerte résolue pourrait repasser en
 * « acquittée ». Les règles sont donc centralisées ici et utilisées à la fois
 * par les actions Filament et par les tests.
 */
class SecurityAlertWorkflow
{
    public const array ACKNOWLEDGABLE_STATUSES = ['new'];

    public const array RESOLVABLE_STATUSES = ['new', 'acknowledged'];

    public static function canAcknowledge(SecurityAlert $alert): bool
    {
        return in_array($alert->getAttribute('status'), self::ACKNOWLEDGABLE_STATUSES, true);
    }

    public static function canResolve(SecurityAlert $alert): bool
    {
        return in_array($alert->getAttribute('status'), self::RESOLVABLE_STATUSES, true);
    }

    /**
     * @throws DomainException si la transition n'est pas autorisée
     */
    public function acknowledge(SecurityAlert $alert, User $user): SecurityAlert
    {
        if (! self::canAcknowledge($alert)) {
            throw new DomainException('Cette alerte ne peut plus être acquittée.');
        }

        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->name,
        ]);

        return $alert->refresh();
    }

    /**
     * @throws DomainException si la transition n'est pas autorisée
     */
    public function resolve(SecurityAlert $alert, User $user): SecurityAlert
    {
        if (! self::canResolve($alert)) {
            throw new DomainException('Cette alerte ne peut plus être résolue.');
        }

        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => $user->name,
        ]);

        return $alert->refresh();
    }
}
