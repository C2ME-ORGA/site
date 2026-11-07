<x-ui.error-page
    eyebrow="Page introuvable"
    title="Cette page n’existe pas"
    lead="Le lien est peut-être obsolète ou l’adresse mal orthographiée. Retrouvez nos services et nos réalisations ci-dessous."
    :image="asset('images/realisations/constans/photo-01.webp')"
    :links="[
        [
            'label' => 'Nos services',
            'href' => route('services.laser'),
            'description' => 'Découpe laser, bureau d’études et charpente métallique.',
        ],
        [
            'label' => 'Nos réalisations',
            'href' => route('projects'),
            'description' => 'Des chantiers livrés en Vendée et dans tout le Grand Ouest.',
        ],
        [
            'label' => 'Demander un devis',
            'href' => route('quote'),
            'description' => 'Décrivez votre besoin, nous revenons vers vous rapidement.',
        ],
    ]"
/>
