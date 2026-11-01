<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use MKWebDesign\FilamentWatchdog\Models\FileIntegrityCheck;
use MKWebDesign\FilamentWatchdog\Services\FileIntegrityService;

/**
 * Remplace la commande `watchdog:baseline` du paquet.
 *
 * Celle du paquet appelle `watchdog:scan --baseline` sans `--force` : avec la
 * surveillance planifiée désactivée (`monitoring.enabled = false`), elle
 * n'écrit rien tout en affichant un succès. Cette version appelle directement
 * le service et renvoie un code de sortie fiable.
 */
class CreateSecurityBaseline extends Command
{
    protected $signature = 'watchdog:baseline';

    protected $description = 'Crée le baseline d’intégrité des fichiers surveillés (même si la planification du paquet est désactivée)';

    public function handle(FileIntegrityService $fileIntegrityService): int
    {
        $this->info('Création du baseline d’intégrité...');

        $before = FileIntegrityCheck::count();

        try {
            $fileIntegrityService->createBaseline();
        } catch (\Throwable $exception) {
            $this->error('Échec de la création du baseline : '.$exception->getMessage());

            return self::FAILURE;
        }

        $after = FileIntegrityCheck::count();
        $monitored = $after;

        if ($monitored === 0) {
            $this->error('Aucun fichier surveillé : vérifier `monitoring.monitored_paths`.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Baseline créé : %d fichiers surveillés (%d nouveaux, %d mis à jour).',
            $monitored,
            max(0, $after - $before),
            max(0, $before - $after),
        ));

        return self::SUCCESS;
    }
}
