<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use MKWebDesign\FilamentWatchdog\Services\EmergencyLockdownService;

/**
 * Lève un verrouillage d'urgence arrivé à expiration.
 *
 * Le paquet stocke l'état et le secret d'accès en cache pendant 24 heures,
 * sans jamais lever le mode maintenance : passé ce délai, l'URL d'urgence ne
 * fonctionne plus alors que le site reste bloqué. Le paquet lit bien
 * `auto_deactivate_hours` mais ne l'utilise nulle part ; cette commande rend
 * la promesse effective et évite un blocage définitif.
 */
class ReleaseExpiredSecurityLockdown extends Command
{
    protected $signature = 'security:lockdown:release';

    protected $description = 'Lève un verrouillage d’urgence de sécurité arrivé à expiration (mode maintenance compris)';

    public function handle(EmergencyLockdownService $lockdownService): int
    {
        $status = $lockdownService->getLockdownStatus();

        if ($status === null) {
            return self::SUCCESS;
        }

        $hours = (int) config('filament-watchdog.emergency_lockdown.auto_deactivate_hours', 24);
        $activatedAt = data_get($status, 'activated_at');

        if ($activatedAt === null) {
            return self::SUCCESS;
        }

        $expiresAt = CarbonImmutable::parse($activatedAt)->addHours($hours);

        if ($expiresAt->isFuture()) {
            return self::SUCCESS;
        }

        $this->warn(sprintf('Verrouillage expiré depuis %s : levée automatique.', $expiresAt->toDateTimeString()));

        $result = $lockdownService->deactivateEmergencyLockdown();

        if (($result['status'] ?? null) !== 'success') {
            $this->error('Échec de la levée automatique : '.($result['error'] ?? 'cause inconnue'));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
