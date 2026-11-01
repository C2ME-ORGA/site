<?php

/*
 * Surveillance de sécurité (mkwebdesign/filament-watchdog-v5).
 *
 * La planification du paquet (`watchdog:scan` chaque minute) est désactivée :
 * le scan est programmé toutes les 15 minutes dans `routes/console.php`, avec
 * `--force` puisque `monitoring.enabled` vaut false. Cela évite de hacher tout
 * le projet à chaque minute et permet de suspendre le scan sur les postes
 * Windows, où le paquet calcule des chemins absolus incompatibles avec sa
 * détection de suppressions.
 *
 * Les chemins surveillés excluent ce qui est généré (build Vite, caches,
 * assets publiés, seeders de démonstration) afin de ne pas produire d'alertes
 * à chaque compilation ou déploiement.
 */
return [
    'monitoring' => [
        'enabled' => false,
        'scan_interval' => 900, // secondes (15 minutes, planifié par l'application)
        'excluded_paths' => [
            'storage',
            'node_modules',
            'vendor',
            '.git',
            '.env',
            'bootstrap/cache',
            'database/seeders',
            'tests',
            'public/build',
            'public/css',
            'public/js/guava',
            'public/images',
            'public/fonts',
            'public/storage',
        ],
        'monitored_paths' => [
            'app',
            'bootstrap',
            'config',
            'database',
            'public',
            'resources',
            'routes',
        ],
    ],

    'file_integrity' => [
        'enabled' => true,
        'hash_algorithm' => 'sha256',
        'store_file_contents' => false,
        'max_file_size' => 50 * 1024 * 1024, // 50 Mo
    ],

    'malware_detection' => [
        'enabled' => true,
        'scan_uploads' => true,
        // Aucun fichier n'est déplacé automatiquement : un faux positif
        // casserait l'application. La quarantaine reste une action manuelle.
        'quarantine_enabled' => false,
        'quarantine_path' => storage_path('app/quarantine'),
        'signatures' => [
            'php_eval' => '/eval\s*\(/i',
            'php_system' => '/system\s*\(/i',
            'php_exec' => '/exec\s*\(/i',
            'php_shell_exec' => '/shell_exec\s*\(/i',
            'php_passthru' => '/passthru\s*\(/i',
            'base64_decode' => '/base64_decode\s*\(/i',
            'file_get_contents' => '/file_get_contents\s*\(\s*[\'"]https?:/i',
            'curl_exec' => '/curl_exec\s*\(/i',
            'web_shell' => '/\$_(?:GET|POST|REQUEST)\s*\[\s*[\'"](?:cmd|command|exec|system)/i',
        ],
    ],

    'activity_monitoring' => [
        'enabled' => true,
        'track_logins' => true,
        'track_admin_actions' => true,
        'track_file_changes' => true,
        'track_database_changes' => true,
        'failed_login_threshold' => 5,
        'ip_whitelist' => [],
    ],

    'alerts' => [
        'enabled' => true,
        'email_enabled' => (bool) env('WATCHDOG_EMAIL_ENABLED', true),
        'email_recipients' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('WATCHDOG_EMAIL_RECIPIENTS', 'admin@c2me.fr')),
        ))),
        'admin_emails' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('WATCHDOG_ADMIN_EMAILS', 'admin@c2me.fr')),
        ))),
        'alert_levels' => [
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'critical' => 4,
        ],
        'rate_limiting' => [
            'enabled' => true,
            'max_alerts_per_hour' => 10,
        ],
    ],

    'emergency_lockdown' => [
        'default_options' => [
            'maintenance_mode' => true,
            'block_ips' => false,
            'disable_users' => false,
            'clear_sessions' => true,
            'htaccess_protection' => false,
            'notify_admins' => true,
            'emergency_backup' => true,
        ],
        'backup_retention_days' => 30,
        'auto_deactivate_hours' => 24,
    ],

    'database' => [
        'connection' => env('DB_CONNECTION', 'mysql'),
        'log_retention_days' => 30,
        'max_log_entries' => 100000,
    ],

    'performance' => [
        'memory_limit' => '512M',
        'execution_time_limit' => 300,
        'batch_size' => 100,
    ],

    'navigation' => [
        'security' => [
            'collapsed' => true,

            /*
             * La navigation anglaise du paquet est masquée : le panneau déclare
             * ses propres entrées françaises dans le groupe « Système »
             * (AdminPanelProvider).
             */
            'only_on_security_pages' => false,
            'hidden' => true,
            'icon' => 'heroicon-o-shield-check',
            'sort' => 100,
        ],
    ],
];
