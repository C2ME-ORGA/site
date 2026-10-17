<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identité de l'entreprise
    |--------------------------------------------------------------------------
    */

    'company' => [
        'name' => 'C2ME',
        'baseline' => 'Construire ensemble, un bâtiment à votre image',
        'sector' => 'Construction métallique',
        'tagline' => 'Découpe laser · Études · Charpente métallique',
        'logo' => [
            'wide' => 'images/logo-wide.png',
            'mark' => 'images/logo-mark.png',
            'stacked' => 'images/logo-stacked.png',
        ],
        'description' => "C2ME réunit la découpe laser, le bureau d'études et la charpente "
            .'métallique pour accompagner vos projets, de la pièce unitaire à la série, '
            .'de la conception au montage sur chantier.',
        'phone' => '02 51 38 16 76',
        'phone_link' => '+33251381676',
        'mobile' => '06 77 95 39 88',
        'mobile_link' => '+33677953988',
        'email' => 'contact@c2me.fr',

        /*
        |----------------------------------------------------------------------
        | Implantations
        |----------------------------------------------------------------------
        |
        | Deux sites distincts : le siège social, où le courrier est adressé,
        | et l'atelier, où les chantiers sont fabriqués et où l'on passe
        | retirer les pièces. Les coordonnées alimentent la carte de contact.
        |
        */

        'locations' => [
            [
                'key' => 'siege',
                'label' => 'Siège social',
                'line1' => 'La Bézochère',
                'line2' => 'La Guyonnière',
                'postal_code' => '85600',
                'city' => 'Montaigu-Vendée',
                'region' => 'Vendée (85)',
                'country_code' => 'FR',
                'coordinates' => [
                    'lat' => 46.9909699,
                    'lng' => -1.2576644,
                ],
            ],

            [
                'key' => 'atelier',
                'label' => 'Atelier',
                'line1' => '2 Rue du Vieux Château',
                'line2' => 'Boufféré',
                'postal_code' => '85600',
                'city' => 'Montaigu-Vendée',
                'region' => 'Vendée (85)',
                'country_code' => 'FR',
                'coordinates' => [
                    'lat' => 46.9654730,
                    'lng' => -1.3354270,
                ],
            ],
        ],

        'zone' => 'France et pays limitrophes',
        'hours' => [
            ['label' => 'Lundi — Vendredi', 'value' => '8 h 00 — 17 h 00'],
            ['label' => 'Samedi', 'value' => 'Sur rendez-vous'],
            ['label' => 'Dimanche', 'value' => 'Fermé'],
        ],

        /*
        |----------------------------------------------------------------------
        | Données structurées
        |----------------------------------------------------------------------
        |
        | Alimente le bloc JSON-LD inséré dans le <head>. Ces valeurs sont lues
        | par les moteurs de recherche et non par les visiteurs : elles doivent
        | rester exactes et être synchronisées avec les horaires ci-dessus.
        |
        | Seuls les créneaux réellement ouverts sans rendez-vous sont déclarés,
        | schema.org n'ayant pas d'équivalent pour « sur rendez-vous ».
        |
        */

        'schema' => [
            'area_served' => 'France',
            'opening_hours' => [
                [
                    'day_of_week' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                    'opens' => '08:00',
                    'closes' => '17:00',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Accès au back-office
    |--------------------------------------------------------------------------
    |
    | Filament refuse l'accès en production tant que le modèle Utilisateur ne
    | précise pas qui peut atteindre le panneau. Seules les adresses listées
    | ici y ont accès ; ajoutez-en pour ouvrir le back-office à un collaborateur.
    |
    */

    'admin' => [
        'allowed_emails' => [
            env('ADMIN_EMAIL', 'admin@c2me.fr'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demandes de devis
    |--------------------------------------------------------------------------
    |
    | Les plans déposés peuvent contenir des données clients : le stockage
    | reste privé et les fichiers sont automatiquement purgés au-delà de la
    | durée de conservation. Les limites de dépôt doivent rester cohérentes
    | avec upload_max_filesize et post_max_size de PHP.
    |
    */

    'quote' => [
        'storage_disk' => 'local',
        'storage_directory' => 'quote-requests',

        'files' => [
            'max_count' => 5,
            'max_size_kb' => 25 * 1024,
            'extensions' => ['pdf', 'dxf', 'dwg'],
        ],

        'retention_months' => 3,

        'rate_limit' => 3,

        /*
        |----------------------------------------------------------------------
        | Parcours « découpe laser »
        |----------------------------------------------------------------------
        |
        | Les plages proposées reprennent les capacités de la machine et la
        | grille tarifaire. Le parcours produit une demande structurée, jamais
        | un prix instantané : la cotation reste humaine.
        |
        */

        'laser' => [
            'materials' => [
                [
                    'label' => 'Acier',
                    'value' => 'acier',
                    'hint' => "Tous types d'acier, notamment S235 et S355.",
                    'max_thickness' => 30,
                    'thicknesses' => [1, 2, 3, 4, 5, 6, 8, 10, 12, 15, 20, 25, 30],
                ],
                [
                    'label' => 'Inox',
                    'value' => 'inox',
                    'hint' => 'Aciers inoxydables.',
                    'max_thickness' => 20,
                    'thicknesses' => [1, 1.5, 2, 3, 4, 5, 6, 8, 10, 12, 15, 20],
                ],
                [
                    'label' => 'Aluminium',
                    'value' => 'aluminium',
                    'hint' => 'Aluminium, découpe à plat.',
                    'max_thickness' => 10,
                    'thicknesses' => [1, 2, 3, 4, 5, 6, 8, 10],
                ],
            ],

            'quantities' => [
                ['label' => 'Pièce unitaire', 'value' => 'unitaire'],
                ['label' => 'Série inférieure à 1 tonne', 'value' => 'moins-1t'],
                ['label' => 'De 1 à moins de 2 tonnes', 'value' => '1-2t'],
                ['label' => 'De 2 à moins de 5 tonnes', 'value' => '2-5t'],
                ['label' => 'De 5 à moins de 10 tonnes', 'value' => '5-10t'],
                ['label' => 'À partir de 10 tonnes', 'value' => 'plus-10t'],
            ],

            'complexities' => [
                ['label' => 'Pièces simples', 'value' => 'simple', 'hint' => 'Contours et découpe standard.'],
                ['label' => 'Pièces complexes', 'value' => 'complexe', 'hint' => 'Formes, ajourages ou percages nombreux.'],
                ['label' => 'Je ne sais pas encore', 'value' => 'inconnu', 'hint' => 'Nous qualifierons ensemble.'],
            ],

            'delays' => [
                ['label' => 'Standard', 'value' => 'standard', 'hint' => 'Délai habituel de fabrication.'],
                ['label' => 'Urgent', 'value' => 'urgent', 'hint' => 'Supplément possible, selon disponibilités.'],
            ],

            'delivery' => [
                ['label' => 'Retrait sur site', 'value' => 'retrait'],
                ['label' => 'Livraison souhaitée', 'value' => 'livraison'],
            ],

            'sheet' => [
                'length' => 4000,
                'width' => 2000,
            ],
        ],

        /*
        |----------------------------------------------------------------------
        | Parcours « bâtiment & charpente »
        |----------------------------------------------------------------------
        |
        | Brief structuré en six étapes. Il ne produit pas de chiffrage
        | instantané : il qualifie le projet pour que le bureau d'études
        | établisse une proposition sur mesure.
        |
        */

        'building' => [
            'types' => [
                [
                    'label' => 'Bâtiment industriel',
                    'value' => 'industriel',
                    'hint' => 'Atelier, entrepôt, unité de production.',
                ],
                [
                    'label' => 'Hangar agricole',
                    'value' => 'agricole',
                    'hint' => 'Matériel, récolte, bétail.',
                ],
                [
                    'label' => 'Bâtiment commercial',
                    'value' => 'commercial',
                    'hint' => 'Showroom, magasin, local d\'activité.',
                ],
                [
                    'label' => 'Charpente & ossature',
                    'value' => 'charpente',
                    'hint' => 'Structure existante à reprendre ou à prolonger.',
                ],
                [
                    'label' => 'Autre projet',
                    'value' => 'autre',
                    'hint' => 'Décrivez votre besoin dans le message.',
                ],
            ],

            'usages' => [
                ['label' => 'Production / atelier', 'value' => 'production'],
                ['label' => 'Stockage', 'value' => 'stockage'],
                ['label' => 'Accueil / ventes', 'value' => 'ventes'],
                ['label' => 'Agriculture', 'value' => 'agriculture'],
                ['label' => 'Multi-usages', 'value' => 'multi'],
                ['label' => 'À définir', 'value' => 'inconnu'],
            ],

            'enveloppes' => [
                ['label' => 'Structure seule', 'value' => 'structure', 'hint' => 'Charpente livrée nue, sans bardage.'],
                ['label' => 'Structure + bardage', 'value' => 'bardage', 'hint' => 'Murs habillés, couverture comprise.'],
                ['label' => 'Bâtiment livré clé en main', 'value' => 'cle-en-main', 'hint' => 'Enveloppe complète, hors second œuvre.'],
                ['label' => 'Je ne sais pas encore', 'value' => 'inconnu', 'hint' => 'Nous vous orienterons.'],
            ],

            'prestations' => [
                ['label' => 'Dessin & ingénierie', 'value' => 'etude', 'hint' => 'Relevé, plans d\'exécution, plans de montage.'],
                ['label' => 'Fabrication', 'value' => 'fabrication', 'hint' => 'Découpe, soudure, traitement anticorrosion.'],
                ['label' => 'Montage sur site', 'value' => 'montage', 'hint' => 'Levage et pose par nos équipes.'],
                ['label' => 'Fondations & dallage', 'value' => 'fondations', 'hint' => 'Coordination avec le lot gros œuvre.'],
                ['label' => 'Livraison', 'value' => 'livraison', 'hint' => 'Transport jusqu\'au chantier.'],
            ],

            'delays' => [
                ['label' => 'À étudier ensemble', 'value' => 'inconnu', 'hint' => 'Aucune contrainte particulière.'],
                ['label' => 'Sous 3 mois', 'value' => 'moins-3m'],
                ['label' => 'Sous 6 mois', 'value' => 'moins-6m'],
                ['label' => 'À l\'année prochaine', 'value' => 'plus-6m'],
            ],

            'budgets' => [
                ['label' => 'À définir', 'value' => 'inconnu'],
                ['label' => 'Moins de 50 000 €', 'value' => 'moins-50k'],
                ['label' => 'De 50 000 à 150 000 €', 'value' => '50-150k'],
                ['label' => 'De 150 000 à 400 000 €', 'value' => '150-400k'],
                ['label' => 'Plus de 400 000 €', 'value' => 'plus-400k'],
            ],

            'levels' => [
                ['label' => 'Maîtrise d\'ouvrage', 'value' => 'moa', 'hint' => 'Vous portez le projet en direct.'],
                ['label' => 'Architecte / bureau d\'études', 'value' => 'bureau', 'hint' => 'Un tiers pilote la conception.'],
                ['label' => 'Entreprise générale', 'value' => 'entreprise', 'hint' => 'Vous coordonnez les lots.'],
                ['label' => 'Particulier', 'value' => 'particulier'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Navigation principale
    |--------------------------------------------------------------------------
    */

    'navigation' => [
        ['label' => 'Accueil', 'route' => 'home'],
        ['label' => 'Entreprise', 'route' => 'company'],
        ['label' => 'Réalisations', 'route' => 'projects'],
        ['label' => 'Contact', 'route' => 'contact'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Les 3 pôles de services (source : fiches C2ME octobre 2026)
    |--------------------------------------------------------------------------
    */

    'services' => [
        [
            'slug' => 'decoupe-laser',
            'route' => 'services.laser',
            'number' => '01',
            'name' => 'Découpe laser',
            'short' => 'De la pièce unitaire à la série. Matière et découpe comprises.',
            'description' => 'Découpe laser fibre 8 kW sur tôle plane, de la pièce unique à la série. '
                .'Acier, inox et aluminium, livraison possible.',
            'lead' => 'De la pièce unitaire à la série, matière et découpe comprises. '
                .'Une machine fibre 8 kW sur un format de travail de 4 000 × 2 000 mm, '
                .'pour tous types de pièces simples comme complexes.',
            'specs' => [
                ['label' => 'Technologie', 'value' => 'Fibre 8 kW'],
                ['label' => 'Longueur', 'value' => '4 000 mm'],
                ['label' => 'Largeur', 'value' => '2 000 mm'],
            ],
            'features' => [
                ['title' => 'Acier', 'text' => "Tous types d'acier, notamment S235 et S355. Jusqu'à 30 mm."],
                ['title' => 'Inox', 'text' => "Jusqu'à 20 mm d'épaisseur."],
                ['title' => 'Aluminium', 'text' => "Jusqu'à 10 mm d'épaisseur."],
                ['title' => 'Pièces urgentes', 'text' => 'Supplément sur devis, selon le délai demandé.'],
            ],
            'blocks' => [
                [
                    'number' => '01',
                    'title' => 'Acier',
                    'subtitle' => "Jusqu'à 30 mm",
                    'text' => "Tous types d'acier, notamment S235 et S355.",
                    'items' => [],
                ],
                [
                    'number' => '02',
                    'title' => 'Inox',
                    'subtitle' => "Jusqu'à 20 mm",
                    'text' => "Aciers inoxydables, jusqu'à 20 mm d'épaisseur.",
                    'items' => [],
                ],
                [
                    'number' => '03',
                    'title' => 'Aluminium',
                    'subtitle' => "Jusqu'à 10 mm",
                    'text' => "Aluminium, jusqu'à 10 mm d'épaisseur.",
                    'items' => [],
                ],
            ],
            'pricing' => [
                'title' => 'Grille tarifaire acier',
                'caption' => 'Prix HT, octobre 2026 — dégressivité selon le tonnage total commandé.',
                'columns' => ['Quantité commandée', 'Tarif HT / kg'],
                'rows' => [
                    ['Pièce unitaire', 'Sur demande'],
                    ['Série inférieure à 1 tonne', 'Nous consulter'],
                    ['De 1 à moins de 2 tonnes', 'Nous consulter'],
                    ['De 2 à moins de 5 tonnes', 'Nous consulter'],
                    ['De 5 à moins de 10 tonnes', 'Nous consulter'],
                    ['À partir de 10 tonnes', 'Nous consulter'],
                ],
                'materials' => [
                    ['label' => 'Inox', 'value' => "Jusqu'à 20 mm"],
                    ['label' => 'Aluminium', 'value' => "Jusqu'à 10 mm"],
                ],
            ],
            'extras' => [
                [
                    'title' => 'Livraison',
                    'text' => 'Livraison possible. Tarif sur devis, selon destination et volume.',
                ],
                [
                    'title' => 'Pièces urgentes',
                    'text' => 'Supplément sur devis, selon le délai demandé et les disponibilités.',
                ],
                [
                    'title' => 'Facturation à la pièce',
                    'text' => 'Pour les tarifs au kg : prix unitaire HT = poids net de la pièce '
                        .'× tarif applicable. Dégressivité selon le tonnage total des pièces commandées.',
                ],
            ],
            'notes' => [
                'Tarifs pour pièces de forme simple. Pièces fortement ajourées, nombreux percages, '
                    .'pertes matière importantes ou reprise de fichiers : sur devis.',
                'Tarifs donnés à titre indicatif, sous réserve de confirmation par devis.',
            ],
            'cta' => [
                'title' => 'Votre projet de découpe laser',
                'text' => 'Transmettez-nous vos plans ou vos cotes : nous vous répondons par un devis, '
                    .'de la pièce unitaire à la série.',
            ],
        ],

        [
            'slug' => 'dessin-ingenierie',
            'route' => 'services.design',
            'number' => '02',
            'name' => 'Dessin & Ingénierie',
            'short' => 'Construction métallique et béton armé.',
            'description' => "Bureau d'études intégré : études de structure, calculs, plans 2D/3D "
                ."et dossiers techniques, de la conception aux plans de fabrication et d'exécution.",
            'lead' => 'Des études adaptées à votre projet, de la conception aux plans de fabrication '
                ."et d'exécution. Construction métallique et béton armé.",
            'specs' => [
                ['label' => 'Conception', 'value' => 'Études de structure'],
                ['label' => 'Modélisation', 'value' => 'Plans 2D / 3D'],
                ['label' => 'Exécution', 'value' => 'Dossiers techniques'],
            ],
            'features' => [
                ['title' => 'Construction métallique', 'text' => 'Ossatures, portiques, poutres et poteaux.'],
                ['title' => 'Béton armé', 'text' => 'Fondations, poutres, poteaux et voiles.'],
                ['title' => 'Calcul de structure', 'text' => 'Stabilité, descentes de charges et assemblages.'],
                ['title' => 'Plans & dossiers', 'text' => 'Plans de fabrication, de montage, de coffrage et de ferraillage.'],
            ],
            'blocks' => [
                [
                    'number' => '01',
                    'title' => 'Construction métallique',
                    'subtitle' => '',
                    'text' => '',
                    'items' => [
                        ['title' => 'Études & dimensionnement', 'text' => 'Ossatures, portiques, poutres et poteaux.'],
                        ['title' => 'Calcul de structure', 'text' => 'Stabilité, descentes de charges et assemblages.'],
                        ['title' => 'Dessin & modélisation', 'text' => "Maquettes 3D et plans d'ensemble."],
                        ['title' => 'Plans de fabrication', 'text' => "Débits, percages et détails d'assemblage."],
                        ['title' => 'Plans de montage', 'text' => 'Repérage des pièces et nomenclatures.'],
                    ],
                ],
                [
                    'number' => '02',
                    'title' => 'Béton armé',
                    'subtitle' => '',
                    'text' => '',
                    'items' => [
                        ['title' => 'Études & dimensionnement', 'text' => 'Fondations, poutres, poteaux et voiles.'],
                        ['title' => 'Calcul de structure', 'text' => 'Descentes de charges et notes de calcul.'],
                        ['title' => 'Plans de coffrage', 'text' => 'Géométrie, niveaux et réservations.'],
                        ['title' => 'Plans de ferraillage', 'text' => 'Armatures, coupes et détails constructifs.'],
                        ['title' => "Dossiers d'exécution", 'text' => "Plans et nomenclatures d'armatures."],
                    ],
                ],
            ],
            'tools' => ['Tekla Structures', 'AutoCAD', 'Robot'],
            'extras' => [
                [
                    'title' => 'Une prestation adaptée à votre besoin',
                    'text' => 'Mission ponctuelle ou dossier complet : périmètre, livrables et délais '
                        .'définis selon votre projet. Études réalisées à partir des plans, charges et '
                        .'données techniques transmis.',
                ],
            ],
            'notes' => [],
            'cta' => [
                'title' => 'Études et dessins sur devis',
                'text' => 'Nous intervenons en mission ponctuelle ou sur dossier complet, '
                    .'selon le périmètre et les livrables de votre projet.',
            ],
        ],

        [
            'slug' => 'charpente-metallique',
            'route' => 'services.frames',
            'number' => '03',
            'name' => 'Charpente métallique',
            'short' => 'Construction, fabrication et montage sur chantier.',
            'description' => 'De la fabrication en atelier au montage sur chantier, C2ME réalise vos '
                .'charpentes et ossatures métalliques pour bâtiments industriels, agricoles et tertiaires.',
            'lead' => 'De la fabrication en atelier au montage sur chantier, C2ME réalise vos '
                .'charpentes et ossatures métalliques.',
            'specs' => [
                ['label' => 'Construction', 'value' => 'Ossatures métalliques'],
                ['label' => 'Fabrication', 'value' => 'Production en atelier'],
                ['label' => 'Montage', 'value' => 'Pose sur chantier'],
            ],
            'features' => [
                ['title' => 'Fabrication en atelier', 'text' => 'Charpentes, ossatures, assemblages et soudure.'],
                ['title' => 'Montage sur chantier', 'text' => 'Pose, assemblage, réglage et alignement.'],
                ['title' => 'Nos moyens', 'text' => 'Banc de perçage, sciage, laser fibre 8 kW.'],
                ['title' => 'Mobilité', 'text' => 'Déplacements sur toute la France et pays limitrophes.'],
            ],
            'blocks' => [
                [
                    'number' => '01',
                    'title' => 'Fabrication en atelier',
                    'subtitle' => '',
                    'text' => '',
                    'items' => [
                        ['title' => 'Charpentes & ossatures', 'text' => 'Portiques, poteaux, poutres et contreventements.'],
                        ['title' => 'Préparation des profils', 'text' => 'Sciage, débit et percage des aciers.'],
                        ['title' => 'Découpe de pièces', 'text' => "Platines, goussets et éléments d'assemblage."],
                        ['title' => 'Assemblage & soudure', 'text' => 'Fabrication des éléments de charpente.'],
                        ['title' => 'Préparation au montage', 'text' => 'Repérage et organisation des pièces.'],
                    ],
                ],
                [
                    'number' => '02',
                    'title' => 'Montage sur chantier',
                    'subtitle' => '',
                    'text' => '',
                    'items' => [
                        ['title' => 'Pose des charpentes', 'text' => 'Mise en place des poteaux et des traverses.'],
                        ['title' => 'Assemblage des structures', 'text' => 'Liaisons boulonnées et fixation des éléments.'],
                        ['title' => 'Ossature secondaire', 'text' => 'Pose des pannes, lisses et contreventements.'],
                        ['title' => 'Réglage & alignement', 'text' => 'Aplomb et positionnement de la charpente.'],
                        ['title' => "Organisation de l'intervention", 'text' => 'Moyens et planning adaptés au chantier.'],
                    ],
                ],
            ],
            'tools' => ['Banc de perçage', 'Sciage', 'Laser fibre 8 kW'],
            'extras' => [
                [
                    'title' => 'Une prestation adaptée à votre besoin',
                    'text' => 'Fabrication seule, montage ou prestation complète pour bâtiments '
                        .'industriels, agricoles et tertiaires. Périmètre, délais et moyens '
                        ."d'intervention définis selon votre projet.",
                ],
                [
                    'title' => "Mobilité & zone d'intervention",
                    'text' => 'Déplacements sur toute la France et dans les pays limitrophes, '
                        .'selon les besoins du chantier.',
                ],
            ],
            'notes' => [],
            'cta' => [
                'title' => 'Fabrication & montage sur devis',
                'text' => 'Périmètre, délais et moyens d\'intervention définis selon votre projet.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Chiffres-clés (placeholders — à confirmer avec l'entreprise)
    |--------------------------------------------------------------------------
    */

    'stats' => [
        ['value' => '8 kW', 'label' => 'Laser fibre'],
        ['value' => '4 000 × 2 000', 'label' => 'Format de découpe (mm)'],
        ['value' => '30 mm', 'label' => 'Acier découpé (max)'],
        ['value' => 'France', 'label' => 'et pays limitrophes'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Arguments de réassurance
    |--------------------------------------------------------------------------
    */

    'highlights' => [
        [
            'title' => 'Un interlocuteur unique',
            'text' => 'Études, découpe, fabrication et montage réalisés en interne.',
        ],
        [
            'title' => 'De la pièce à la série',
            'text' => 'De la pièce unitaire à la production en série, sans rupture de charge.',
        ],
        [
            'title' => 'Atelier intégré',
            'text' => 'Laser fibre 8 kW, banc de perçage et sciage sur un même site.',
        ],
        [
            'title' => 'Qualité en cours de certification',
            'text' => 'Démarche qualité et traçabilité (norme EN 1090 en cours).',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Réalisations — intitulés par dossier (source : F:\C2ME\Travaux)
    |--------------------------------------------------------------------------
    */

    'projects' => [
        '3P' => '3P',
        'Albi' => 'Albi',
        'bbq' => 'BBQ',
        'Bordeau' => 'Bordeau',
        'constans' => 'Constans',
        'dussac' => 'Dussac',
        'Escala' => 'Escala',
        'Lacroix' => 'Lacroix',
        'Minaudiere' => 'Minaudière',
        'Monestre' => 'Monestre',
        'protextyl' => 'Protextyl',
        'Soucha' => 'Soucha',
        'valastro' => 'Valastro',
        'verges' => 'Verges',
    ],

];
