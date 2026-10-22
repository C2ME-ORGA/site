<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Champs spécifiques par carnet
    |--------------------------------------------------------------------------
    |
    | Les comptes partagent un socle commun ; ces champs complémentaires sont
    | stockés dans la colonne JSON « metadata » et rendus dynamiquement dans
    | le formulaire Filament selon la catégorie sélectionnée.
    |
    */

    'fields' => [
        'prescripteurs' => [
            ['key' => 'specialty', 'label' => 'Spécialité', 'type' => 'text'],
            ['key' => 'intervention_area', 'label' => 'Zone d’intervention', 'type' => 'text'],
            ['key' => 'referrer', 'label' => 'Apporteur d’affaires', 'type' => 'text'],
            ['key' => 'commission_rate', 'label' => 'Commission (%)', 'type' => 'number'],
            ['key' => 'missions', 'label' => 'Types de missions', 'type' => 'textarea'],
        ],

        'clients_industriels' => [
            ['key' => 'sites', 'label' => 'Sites', 'type' => 'text'],
            ['key' => 'industry', 'label' => 'Secteur', 'type' => 'text'],
            ['key' => 'headcount', 'label' => 'Effectif', 'type' => 'number'],
            ['key' => 'maintenance_mode', 'label' => 'Maintenance', 'type' => 'select', 'options' => [
                'interne' => 'Interne',
                'externe' => 'Externe',
                'mixte' => 'Mixte',
            ]],
            ['key' => 'shutdown_frequency', 'label' => 'Fréquence d’arrêt', 'type' => 'text'],
            ['key' => 'equipment', 'label' => 'Équipements', 'type' => 'textarea'],
        ],

        'entreprises_batiment' => [
            ['key' => 'trades', 'label' => 'Corps d’état', 'type' => 'text'],
            ['key' => 'company_size', 'label' => 'Taille', 'type' => 'text'],
            ['key' => 'intervention_area', 'label' => 'Zone d’intervention', 'type' => 'text'],
            ['key' => 'site_types', 'label' => 'Types de chantiers', 'type' => 'textarea'],
            ['key' => 'qualifications', 'label' => 'Qualifications', 'type' => 'text'],
            ['key' => 'insurance', 'label' => 'Assurance décennale', 'type' => 'text'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Devis
    |--------------------------------------------------------------------------
    */

    'bank' => [
        'name' => env('CRM_BANK_NAME', 'BPAV'),
        'rib' => env('CRM_BANK_RIB', '13807008203342135909658'),
        'iban' => env('CRM_BANK_IBAN', 'FR7613807008203342135909658'),
        'bic' => env('CRM_BANK_BIC', 'CCBPFRPPNAN'),
    ],

    'quotes' => [
        'storage_disk' => 'local',
        'storage_directory' => 'quotes',
        'validity_days' => 7,
        'default_vat_rate' => 0,
        'default_payment_method' => 'Virement',
        'units' => [
            'u' => 'Unité',
            'kg' => 'Kilogramme',
            'm2' => 'Mètre carré',
            'ml' => 'Mètre linéaire',
            'h' => 'Heure',
            'forfait' => 'Forfait',
        ],
    ],

    'dashboard' => [
        'monthly_revenue_target' => (float) env('CRM_DASHBOARD_MONTHLY_TARGET', 0),
        'quotes_to_follow_up_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Email
    |--------------------------------------------------------------------------
    |
    | Deux types de boîtes : une boîte partagée commercial@c2me.fr et une
    | boîte personnelle par utilisateur, chez Orange.
    |
    */

    'email' => [
        'shared_mailbox' => env('CRM_SHARED_MAILBOX', 'commercial@c2me.fr'),
        'sync_interval_minutes' => 5,
        'sync_batch_size' => 50,
        'notifications' => (bool) env('CRM_EMAIL_NOTIFICATIONS', true),
        'retention_months' => (int) env('CRM_EMAIL_RETENTION_MONTHS', 36),
        'imap' => [
            'host' => env('CRM_IMAP_HOST', 'imap.orange.fr'),
            'port' => (int) env('CRM_IMAP_PORT', 993),
            'encryption' => env('CRM_IMAP_ENCRYPTION', 'ssl'),
        ],
        'smtp' => [
            'host' => env('CRM_SMTP_HOST', 'smtp.orange.fr'),
            'port' => (int) env('CRM_SMTP_PORT', 465),
            'encryption' => env('CRM_SMTP_ENCRYPTION', 'ssl'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Résolution des demandes entrantes
    |--------------------------------------------------------------------------
    |
    | Carnet et catégorie proposés par défaut lorsqu'une demande de devis ou
    | de contact du site est transformée en compte, contact et affaire. Ces
    | valeurs peuvent être ajustées dans la fenêtre de conversion.
    |
    */

    'requests' => [
        'quote' => [
            'laser' => [
                'book' => 'clients_industriels',
                'category' => 'usines',
            ],
            'building' => [
                'book' => 'entreprises_batiment',
                'category' => 'entreprises_generales',
            ],
        ],

        'contact' => [
            'book' => 'clients_industriels',
            'category' => 'usines',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conservation des données (RGPD)
    |--------------------------------------------------------------------------
    */

    'retention' => [
        'prospects_months' => (int) env('CRM_RETENTION_PROSPECTS_MONTHS', 36),
        'activities_months' => (int) env('CRM_RETENTION_ACTIVITIES_MONTHS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Calendrier et relances
    |--------------------------------------------------------------------------
    */

    'calendar' => [
        'default_view' => 'month',
        'workday_start' => 8,
        'workday_end' => 18,
    ],

    /*
    |--------------------------------------------------------------------------
    | Relances automatiques
    |--------------------------------------------------------------------------
    |
    | Les comptes dont next_follow_up_at arrive à échéance génèrent un rappel
    | la veille (17:00) et le jour J (08:00). Les horaires sont exprimés dans
    | le fuseau de l'application (Europe/Paris).
    |
    */

    'follow_ups' => [
        'enabled' => (bool) env('CRM_FOLLOW_UPS_ENABLED', true),
        'day_before_time' => env('CRM_FOLLOW_UPS_DAY_BEFORE_TIME', '17:00'),
        'due_day_time' => env('CRM_FOLLOW_UPS_DUE_DAY_TIME', '08:00'),
        'mail_enabled' => (bool) env('CRM_FOLLOW_UPS_MAIL', true),
        'exclude_inactive' => true,
        'retry_days' => 7,
        'max_notification_items' => 5,
        'batch_size' => 100,
    ],

];
