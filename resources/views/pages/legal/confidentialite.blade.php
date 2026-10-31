@php
    $site = config('site');
    $editor = $site['legal']['editor'];
    $host = $site['legal']['host'];

    /*
     * Durées lues dans la configuration et jamais saisies ici : la page ne
     * peut donc pas contredire la purge réellement planifiée si un délai
     * évolue. La commande quotes:purge tourne à 3 h, contacts:purge à 3 h 15.
     */
    $quoteMonths = (int) $site['quote']['retention_months'];
    $contactMonths = (int) $site['contact']['retention_months'];

    /**
     * Sommaire : id d'ancre => libellé.
     *
     * @var array<string, string> $anchors
     */
    $anchors = [
        'responsable' => 'Responsable du traitement',
        'donnees' => 'Données collectées',
        'finalites' => 'Finalités et bases légales',
        'conservation' => 'Durées de conservation',
        'destinataires' => 'Destinataires',
        'cookies' => 'Cookies et services tiers',
        'securite' => 'Sécurité',
        'droits' => 'Vos droits',
    ];
@endphp

<x-layouts.app
    title="Politique de confidentialité"
    description="Données collectées sur c2me.fr, finalités, durées de conservation, cookies et exercice de vos droits."
>

    <x-ui.page-hero
        eyebrow="Données personnelles"
        title="Politique de confidentialité"
        lead="Ce que nous recueillons, pourquoi, combien de temps nous le conservons et comment exercer vos droits."
    />

    <section class="py-16 lg:py-20">
        <div class="container-site grid gap-12 lg:grid-cols-12">
            {{-- Sommaire --}}
            <nav class="lg:col-span-3" aria-label="Sommaire de la politique de confidentialité">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-brand-600">Sommaire</p>

                <ol class="mt-4 space-y-1 border-l border-mist-200">
                    @foreach ($anchors as $id => $label)
                        <li>
                            <a
                                href="#{{ $id }}"
                                class="-ml-px block border-l border-transparent py-1.5 pl-4 text-sm leading-snug text-ink-700/70 transition hover:border-brand-500 hover:text-ink-900"
                            >{{ $label }}</a>
                        </li>
                    @endforeach
                </ol>
            </nav>

            <div class="space-y-14 lg:col-span-9 lg:max-w-3xl">
                {{-- RESPONSABLE --}}
                <x-ui.legal-section id="responsable" title="Responsable du traitement">
                    <p>
                        Le responsable du traitement des données collectées sur c2me.fr est
                        <strong class="font-semibold text-ink-900">{{ $editor['legal_name'] }}</strong>,
                        {{ $editor['legal_form'] }} au capital de {{ $editor['capital'] }}, dont le siège social est
                        situé {{ $editor['address'] }}.
                    </p>
                    <p>
                        Pour toute question relative à vos données, écrivez à
                        <a href="mailto:{{ $editor['email'] }}" class="font-semibold text-brand-600 hover:text-brand-700">
                            {{ $editor['email'] }}
                        </a>.
                    </p>
                </x-ui.legal-section>

                {{-- DONNÉES COLLECTÉES --}}
                <x-ui.legal-section id="donnees" title="Données collectées">
                    <p>
                        Le site ne collecte que les informations que vous saisissez volontairement dans ses
                        formulaires, auxquelles s’ajoutent quelques éléments techniques enregistrés automatiquement
                        pour la sécurité du service.
                    </p>

                    <div class="space-y-3">
                        <h3 class="pt-1 text-base font-bold text-ink-900">Formulaire de contact</h3>
                        <ul class="list-disc space-y-1.5 pl-5 marker:text-brand-500">
                            <li>Nom, société, adresse e-mail et téléphone</li>
                            <li>Type de demande et contenu du message</li>
                            <li>Données techniques : adresse IP, page d’origine</li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="pt-3 text-base font-bold text-ink-900">Demande de devis</h3>
                        <ul class="list-disc space-y-1.5 pl-5 marker:text-brand-500">
                            <li>Nom, société, adresse e-mail, téléphone et commune</li>
                            <li>
                                Détails du parcours de demande : matière, épaisseur, quantité, complexité, délais et
                                livraison pour la découpe laser ; nature du bâtiment, implantation, enveloppe,
                                prestations, calendrier et budget pour la construction
                            </li>
                            <li>Contenu du message</li>
                            <li>
                                <strong class="font-semibold text-ink-900">Plans joints</strong> aux formats PDF, DXF
                                ou DWG, dans la limite de 5 fichiers et de 25 Mo par fichier
                            </li>
                            <li>Données techniques : adresse IP, page d’origine</li>
                        </ul>
                    </div>

                    <p class="text-sm text-ink-700/70">
                        Les champs non marqués comme obligatoires restent facultatifs. Aucune donnée n’est collectée
                        par le biais d’un compte utilisateur : le site ne demande aucune inscription.
                    </p>
                </x-ui.legal-section>

                {{-- FINALITÉS --}}
                <x-ui.legal-section id="finalites" title="Finalités et bases légales">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[34rem] border-collapse text-left align-top">
                            <thead>
                                <tr class="border-b border-ink-900/15">
                                    <th scope="col" class="py-2.5 pr-4 text-xs font-bold uppercase tracking-[0.1em] text-ink-700/70">Traitement</th>
                                    <th scope="col" class="py-2.5 pr-4 text-xs font-bold uppercase tracking-[0.1em] text-ink-700/70">Finalité</th>
                                    <th scope="col" class="py-2.5 text-xs font-bold uppercase tracking-[0.1em] text-ink-700/70">Base légale</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-mist-200">
                                <tr>
                                    <td class="py-3.5 pr-4 font-semibold text-ink-900">Formulaire de contact</td>
                                    <td class="py-3.5 pr-4">Répondre à votre demande</td>
                                    <td class="py-3.5">Intérêt légitime — art. 6.1.f RGPD</td>
                                </tr>
                                <tr>
                                    <td class="py-3.5 pr-4 font-semibold text-ink-900">Formulaire de devis</td>
                                    <td class="py-3.5 pr-4">Étudier votre besoin et établir le devis demandé</td>
                                    <td class="py-3.5">Mesures précontractuelles — art. 6.1.b RGPD</td>
                                </tr>
                                <tr>
                                    <td class="py-3.5 pr-4 font-semibold text-ink-900">Notifications e-mail</td>
                                    <td class="py-3.5 pr-4">Alerter l’équipe qu’une demande est déposée</td>
                                    <td class="py-3.5">Intérêt légitime — art. 6.1.f RGPD</td>
                                </tr>
                                <tr>
                                    <td class="py-3.5 pr-4 font-semibold text-ink-900">Facturation</td>
                                    <td class="py-3.5 pr-4">Suivre et facturer une commande acceptée</td>
                                    <td class="py-3.5">Obligation légale — art. 6.1.c RGPD</td>
                                </tr>
                                <tr>
                                    <td class="py-3.5 pr-4 font-semibold text-ink-900">Lutte contre les abus</td>
                                    <td class="py-3.5 pr-4">Limiter les envois automatisés : 3 messages par heure et par adresse</td>
                                    <td class="py-3.5">Intérêt légitime — art. 6.1.f RGPD</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p>Vous n’êtes soumis à aucune décision automatisée produisant des effets juridiques.</p>
                </x-ui.legal-section>

                {{-- CONSERVATION --}}
                <x-ui.legal-section id="conservation" title="Durées de conservation">
                    <ul class="list-disc space-y-3 pl-5 marker:text-brand-500">
                        <li>
                            <strong class="font-semibold text-ink-900">Plans joints à une demande de devis</strong>
                            — {{ $quoteMonths }} mois. Ils sont ensuite supprimés du stockage, puis la demande est
                            effacée de la base.
                        </li>
                        <li>
                            <strong class="font-semibold text-ink-900">Demande de devis</strong>
                            — {{ $quoteMonths }} mois, la suppression des fichiers précédant celle de l’enregistrement.
                        </li>
                        <li>
                            <strong class="font-semibold text-ink-900">Demandes de contact</strong>
                            — {{ $contactMonths }} mois.
                        </li>
                        <li>
                            <strong class="font-semibold text-ink-900">Factures et pièces comptables</strong>
                            — 10 ans, conformément aux obligations légales.
                        </li>
                    </ul>

                    <p>
                        Les deux premières durées s’appliquent automatiquement : une tâche planifiée supprime chaque
                        nuit les demandes expirées et leurs fichiers, sans intervention manuelle. Passé ces délais,
                        vos données sont définitivement effacées.
                    </p>
                </x-ui.legal-section>

                {{-- DESTINATAIRES --}}
                <x-ui.legal-section id="destinataires" title="Destinataires">
                    <p>
                        Vos données sont destinées exclusivement à l’équipe <strong class="font-semibold text-ink-900">{{ $editor['legal_name'] }}</strong>.
                        Elles ne sont ni vendues, ni louées, ni transmises à des fins publicitaires.
                    </p>
                    <p>
                        Deux prestataires interviennent en tant que sous-traitants, au sens de l’article 28 du RGPD :
                        l’hébergeur {{ $host['name'] }}, qui stocke les données du site, et le prestataire de
                        messagerie qui achemine les notifications d’équipe. Chacun agit uniquement sur instruction de
                        {{ $editor['legal_name'] }} et n’est pas autorisé à les exploiter pour son propre compte.
                    </p>
                    <p>Aucun transfert de données en dehors de l’Union européenne n’est organisé par le site.</p>
                </x-ui.legal-section>

                {{-- COOKIES --}}
                <x-ui.legal-section id="cookies" title="Cookies et services tiers">
                    <p>
                        Le site ne dépose qu’un seul cookie strictement nécessaire à son fonctionnement : le cookie de
                        session <code class="rounded bg-mist-100 px-1.5 py-0.5 text-sm text-ink-900">c2me-session</code>.
                        Il est supprimé à la fermeture de votre navigateur et ne sert qu’à maintenir l’état des
                        formulaires en cours de remplissage.
                    </p>

                    <div>
                        <h3 class="pt-3 text-base font-bold text-ink-900">Ce que le site ne fait pas</h3>
                        <ul class="list-disc space-y-1.5 pl-5 marker:text-brand-500">
                            <li>Aucune mesure d’audience, aucun outil statistique de suivi</li>
                            <li>Aucun traceur publicitaire ni réseau social</li>
                            <li>Aucun profilage ni personnalisation de contenu</li>
                            <li>
                                Polices de caractères servies depuis le site lui-même — aucune requête n’est envoyée à
                                Google Fonts ni à un autre fournisseur tiers
                            </li>
                        </ul>
                    </div>

                    <div>
                        <h3 class="pt-3 text-base font-bold text-ink-900">Service tiers intégré</h3>
                        <p>
                            La page contact affiche une carte OpenStreetMap fournie par openstreetmap.org. Ce tiers
                            reçoit votre adresse IP pour servir l’image et peut déposer des cookies selon sa propre
                            politique. Le reste du site ne comporte aucune autre intégration externe.
                        </p>
                    </div>
                </x-ui.legal-section>

                {{-- SÉCURITÉ --}}
                <x-ui.legal-section id="securite" title="Sécurité">
                    <p>
                        Les plans déposés sont conservés sur un stockage privé, hors de la racine publique du site :
                        ils ne sont accessibles par aucune adresse connue, et leur dépôt est limité à 5 fichiers de
                        25 Mo au maximum.
                    </p>
                    <p>
                        Les formulaires sont soumis à une limitation de débit, et les demandes de devis sont purgées
                        automatiquement à échéance. L’accès à l’ensemble des demandes est réservé aux seuls comptes
                        habilités du back-office.
                    </p>
                </x-ui.legal-section>

                {{-- DROITS --}}
                <x-ui.legal-section id="droits" title="Vos droits">
                    <p>
                        Conformément au RGPD, vous disposez d’un droit d’accès à vos données, de rectification,
                        d’effacement, de limitation du traitement, d’opposition et de portabilité. Vous pouvez
                        exercer ces droits à tout moment et gratuitement, sans avoir à motiver votre demande.
                    </p>
                    <p>
                        Écrivez à
                        <a href="mailto:{{ $editor['email'] }}" class="font-semibold text-brand-600 hover:text-brand-700">
                            {{ $editor['email'] }}
                        </a>
                        ou par courrier à {{ $editor['legal_name'] }}, {{ $editor['address'] }}. Une preuve
                        d’identité pourra vous être demandée uniquement en cas de doute raisonnable.
                    </p>
                    <p>
                        Si vous estimez, après nous avoir contactés, que vos droits ne sont pas respectés, vous
                        pouvez adresser une réclamation à la CNIL, 3 place de Fontenoy, TSA 80715, 75334 Paris Cedex
                        07, ou sur
                        <a href="https://www.cnil.fr" target="_blank" rel="noopener noreferrer" class="font-semibold text-brand-600 hover:text-brand-700">
                            cnil.fr
                        </a>.
                    </p>
                    <p class="text-sm text-ink-700/70">
                        Dernière mise à jour : <time datetime="2026-10-17">17 octobre 2026</time>.
                    </p>
                </x-ui.legal-section>
            </div>
        </div>
    </section>
</x-layouts.app>
