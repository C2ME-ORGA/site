<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;

/*
|--------------------------------------------------------------------------
| Sauvegarde de la base de données
|--------------------------------------------------------------------------
|
| Seule la base est incluse dans les archives : les fichiers privés
| (plans, photos, pièces jointes, PDF) et les images publiques sont
| sauvegardés séparément, avec une simple archive tar. La procédure
| complète est décrite dans docs/sauvegarde-restauration.md.
|
*/

return [

    /*
     * Interrupteur général : les tâches planifiées de sauvegarde ne
     * s'exécutent que si cette valeur est vraie.
     */
    'enabled' => env('BACKUP_ENABLED', true),

    'backup' => [

        /*
         * Nom de l'application utilisé pour nommer et surveiller les
         * sauvegardes.
         */
        'name' => env('BACKUP_NAME', 'c2me'),

        'source' => [

            /*
             * Aucun fichier n'est inclus : l'archive ne contient que le
             * dump SQL. Les fichiers sont sauvegardés par la procédure
             * décrite dans docs/sauvegarde-restauration.md.
             */
            'files' => [
                'include' => [],

                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    storage_path('framework'),
                ],

                'follow_links' => false,

                'ignore_unreadable_directories' => false,

                'relative_path' => null,
            ],

            /*
             * Connexion sauvegardée. Le projet utilise MySQL ; le dump
             * s'appuie sur mysqldump, qui doit être installé sur le
             * serveur (paquet mysql-client ou default-mysql-client).
             */
            'databases' => [
                'mysql',
            ],
        ],

        /*
         * Le dump est déjà compressé par l'archive ZIP : pas de gzip
         * supplémentaire pour éviter de décompresser deux fois lors
         * d'une restauration.
         */
        'database_dump_compressor' => null,

        'database_dump_file_timestamp_format' => null,

        'database_dump_filename_base' => 'database',

        'database_dump_file_extension' => '',

        'destination' => [

            'compression_method' => ZipArchive::CM_DEFAULT,

            'compression_level' => 9,

            /*
             * Préfixe des archives : c2me-2026-10-31-01-30-00.zip.
             */
            'filename_prefix' => 'c2me-',

            /*
             * Disque défini dans config/filesystems.php, sous
             * storage/app/backups : jamais accessible depuis le web.
             */
            'disks' => [
                (string) env('BACKUP_DISK', 'backups'),
            ],

            'continue_on_failure' => false,
        ],

        /*
         * Répertoire de travail du dump, supprimé après l'archive.
         */
        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * Mot de passe de chiffrement de l'archive (AES-256). Il est
         * fortement conseillé en production : l'archive contient des
         * données personnelles. Sans mot de passe, l'archive n'est pas
         * chiffrée (pratique en local uniquement).
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => env('BACKUP_ARCHIVE_PASSWORD') ? 'default' : 'none',

        /*
         * Vérifie que l'archive peut être ouverte et contient bien le
         * dump avant de la déclarer réussie.
         */
        'verify_backup' => true,

        'tries' => 1,

        'retry_delay' => 0,
    ],

    /*
     * Seuls les échecs sont notifiés : un succès quotidien ne doit pas
     * encombrer la boîte de réception. Les notifications partent vers
     * BACKUP_NOTIFICATION_EMAIL, ADMIN_EMAIL par défaut.
     */
    'notifications' => [

        /*
         * Les six classes doivent rester déclarées : le package instancie
         * la notification de chaque événement et lit ses canaux ici. Un
         * tableau vide désactive l'envoi correspondant.
         */
        'notifications' => [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            CleanupHasFailedNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', env('ADMIN_EMAIL', 'admin@c2me.fr')),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'contact@c2me.fr'),
                'name' => env('MAIL_FROM_NAME', 'C2ME'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],

        'webhook' => [
            'url' => '',
        ],
    ],

    /*
     * Canal de journalisation des sauvegardes (`null` = canal par
     * défaut de l'application).
     */
    'log_channel' => null,

    /*
     * `backup:monitor` signale une sauvegarde absente depuis plus de
     * deux jours ou un stockage supérieur à 2 Go.
     */
    'monitor_backups' => [
        [
            'name' => env('BACKUP_NAME', 'c2me'),
            'disks' => [
                (string) env('BACKUP_DISK', 'backups'),
            ],
            'health_checks' => [
                MaximumAgeInDays::class => 2,
                MaximumStorageInMegabytes::class => 2000,
            ],
        ],
    ],

    'cleanup' => [

        'strategy' => DefaultStrategy::class,

        'default_strategy' => [

            /*
             * Toutes les sauvegardes des 7 derniers jours sont
             * conservées, puis une par jour pendant 30 jours, une par
             * semaine pendant 12 semaines, une par mois pendant 6 mois
             * et une par an pendant 2 ans.
             */
            'keep_all_backups_for_days' => 7,

            'keep_daily_backups_for_days' => 30,

            'keep_weekly_backups_for_weeks' => 12,

            'keep_monthly_backups_for_months' => 6,

            'keep_yearly_backups_for_years' => 2,

            /*
             * Garde-fou : au-delà de 2 Go, les archives les plus
             * anciennes sont supprimées en priorité.
             */
            'delete_oldest_backups_when_using_more_megabytes_than' => 2000,
        ],

        'tries' => 1,

        'retry_delay' => 0,
    ],

];
