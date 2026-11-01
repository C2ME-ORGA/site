<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="login-url" content="{{ route('filament.admin.auth.login') }}">
    <meta name="theme-color" content="#0e8577">
    <meta name="offline-version" content="c2me-v6">
    <title>Mode terrain — C2ME</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/images/logo-mark.png" type="image/png">
    <script>
        // Applique le thème avant le premier rendu pour éviter un flash clair.
        (() => {
            try {
                const stored = localStorage.getItem('c2me-field-theme');
                const dark = stored
                    ? stored === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;

                document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            } catch (error) {
                // Le thème système reste appliqué par la feuille de style.
            }
        })();
    </script>
    <style>
        :root {
            --brand: #0e8577;
            --brand-dark: #0b6d6d;
            --ink: #0d2b33;
            --muted: #5b6b7c;
            --line: #e2e8f0;
            --bg: #f8fafc;
            --warn: #b45309;
            --surface: #ffffff;
            --surface-soft: #fcfdff;
            --focus: #0b6d6d;
            --danger-bg: #fff1f2;
            --danger-border: #fecaca;
            --danger-ink: #991b1b;
            --danger-strong: #b91c1c;
            --pill-bg: #e7f7f7;
            --pill-ink: #0b6d6d;
            --pill-pending-bg: #fef3c7;
            --pill-pending-ink: #92400e;
            --pill-danger-bg: #fee2e2;
            --pill-danger-ink: #991b1b;
            --pill-warning-bg: #ffedd5;
            --pill-warning-ink: #9a3412;
            --pill-tour-bg: #e0f2fe;
            --pill-tour-ink: #075985;
            --toast-bg: #0d2b33;
            --toast-ink: #ffffff;
            color-scheme: light;
        }

        :root[data-theme="dark"] {
            --brand-dark: #7fe0d6;
            --ink: #e2e8f0;
            --muted: #9fb0c0;
            --line: #1e2f36;
            --bg: #0b1418;
            --surface: #102229;
            --surface-soft: #0d1c23;
            --focus: #7fe0d6;
            --danger-bg: #2a1416;
            --danger-border: #5b1f26;
            --danger-ink: #fecaca;
            --danger-strong: #f87171;
            --pill-bg: #0b3b39;
            --pill-ink: #7fe0d6;
            --pill-pending-bg: #3a2a0a;
            --pill-pending-ink: #fcd34d;
            --pill-danger-bg: #3f171a;
            --pill-danger-ink: #fecaca;
            --pill-warning-bg: #3a2410;
            --pill-warning-ink: #fdba74;
            --pill-tour-bg: #0c3448;
            --pill-tour-ink: #7dd3fc;
            --toast-bg: #e2e8f0;
            --toast-ink: #0b1418;
            color-scheme: dark;
        }

        @media (prefers-color-scheme: dark) {
            :root:not([data-theme="light"]) {
                --brand-dark: #7fe0d6;
                --ink: #e2e8f0;
                --muted: #9fb0c0;
                --line: #1e2f36;
                --bg: #0b1418;
                --surface: #102229;
                --surface-soft: #0d1c23;
                --focus: #7fe0d6;
                --danger-bg: #2a1416;
                --danger-border: #5b1f26;
                --danger-ink: #fecaca;
                --danger-strong: #f87171;
                --pill-bg: #0b3b39;
                --pill-ink: #7fe0d6;
                --pill-pending-bg: #3a2a0a;
                --pill-pending-ink: #fcd34d;
                --pill-danger-bg: #3f171a;
                --pill-danger-ink: #fecaca;
                --pill-warning-bg: #3a2410;
                --pill-warning-ink: #fdba74;
                --pill-tour-bg: #0c3448;
                --pill-tour-ink: #7dd3fc;
                --toast-bg: #e2e8f0;
                --toast-ink: #0b1418;
                color-scheme: dark;
            }
        }

        * { box-sizing: border-box; }

        html { -webkit-text-size-adjust: 100%; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 15px;
            line-height: 1.45;
        }

        .skip-link a {
            position: absolute;
            top: -100px;
            left: 0.75rem;
            z-index: 100;
            padding: 0.65rem 1rem;
            border-radius: 0.5rem;
            background: var(--surface);
            color: var(--ink);
            font-weight: 600;
        }

        .skip-link a:focus { top: 0.75rem; }

        :focus-visible {
            outline: 3px solid var(--focus);
            outline-offset: 2px;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        header {
            position: sticky;
            top: 0;
            z-index: 30;
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem 0.75rem;
            align-items: center;
            justify-content: space-between;
            padding: 0.55rem 0.85rem;
            background: var(--surface);
            border-bottom: 1px solid var(--line);
        }

        .brand { display: flex; align-items: center; gap: 0.5rem; font-weight: 700; min-width: 0; }
        .brand img { width: 32px; height: 32px; }
        .brand__text { white-space: nowrap; }

        .toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.35rem; min-width: 0; }

        .status { display: flex; align-items: center; gap: 0.35rem; color: var(--muted); font-size: 0.8rem; min-width: 0; }
        .status__text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 12rem; }
        .dot { flex: none; width: 0.55rem; height: 0.55rem; border-radius: 999px; background: #16a34a; }
        .dot--off { background: var(--warn); }

        button, .button {
            appearance: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            min-height: 40px;
            border: 1px solid var(--line);
            background: var(--surface);
            color: var(--ink);
            border-radius: 0.5rem;
            padding: 0.45rem 0.8rem;
            font: inherit;
            font-size: 0.875rem;
            line-height: 1.2;
            cursor: pointer;
            text-decoration: none;
        }

        button.primary { background: var(--brand); border-color: var(--brand); color: #fff; }
        button:disabled { opacity: 0.55; cursor: not-allowed; }
        button.danger { color: var(--danger-strong); }

        .icon-button { width: 40px; height: 40px; padding: 0; flex: none; }
        .icon-button svg { width: 20px; height: 20px; }
        .only-narrow { display: none; }
        button[hidden], .tab__badge[hidden], .install-banner[hidden] { display: none !important; }
        .icon-sun { display: none; }
        :root[data-theme="dark"] .icon-moon { display: none; }
        :root[data-theme="dark"] .icon-sun { display: block; }

        main { max-width: 60rem; margin: 0 auto; padding: 1rem; }

        .tab-panel[hidden] { display: none; }

        .tabbar { display: none; }

        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem 1.5rem;
            padding: 0.85rem 1rem;
            margin-bottom: 1rem;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 0.75rem;
            font-size: 0.875rem;
            color: var(--muted);
        }

        .search {
            width: 100%;
            padding: 0.7rem 0.9rem;
            margin-bottom: 1rem;
            border: 1px solid var(--line);
            border-radius: 0.6rem;
            font: inherit;
            background: var(--surface);
            color: var(--ink);
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        select, input[type="text"], input[type="email"], input[type="tel"], input[type="datetime-local"], input[type="search"] {
            width: 100%;
            min-height: 40px;
            padding: 0.5rem 0.65rem;
            border: 1px solid var(--line);
            border-radius: 0.5rem;
            font: inherit;
            background: var(--surface);
            color: var(--ink);
        }

        .filters label.check {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.5rem;
            font-size: 0.85rem;
        }

        .quick-actions { display: flex; flex-wrap: wrap; gap: 0.4rem; margin: 0.75rem 0; }
        .button.small, button.small { padding: 0.35rem 0.6rem; font-size: 0.8rem; min-height: 34px; }

        .pill {
            display: inline-block;
            padding: 0.1rem 0.5rem;
            border-radius: 999px;
            background: var(--pill-bg);
            color: var(--pill-ink);
            font-size: 0.75rem;
            font-weight: 600;
        }

        .pill--pending { background: var(--pill-pending-bg); color: var(--pill-pending-ink); }
        .pill--danger { background: var(--pill-danger-bg); color: var(--pill-danger-ink); }
        .pill--warning { background: var(--pill-warning-bg); color: var(--pill-warning-ink); }
        .pill--tour { background: var(--pill-tour-bg); color: var(--pill-tour-ink); }

        .banner {
            padding: 0.7rem 1rem;
            background: var(--danger-bg);
            border-bottom: 1px solid var(--danger-border);
            color: var(--danger-ink);
            font-size: 0.875rem;
        }

        .banner a { color: inherit; font-weight: 700; }

        .install-banner {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem 1rem;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding: 0.8rem 1rem;
            background: var(--pill-bg);
            border: 1px solid var(--line);
            border-radius: 0.75rem;
        }

        .install-banner strong { color: var(--pill-ink); }

        .conflicts {
            margin-bottom: 1rem;
            padding: 0.85rem 1rem;
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            border-radius: 0.75rem;
        }

        .conflicts h2 {
            margin: 0 0 0.5rem;
            font-size: 0.9rem;
            text-transform: none;
            letter-spacing: 0;
            color: var(--danger-ink);
        }

        .conflict { padding: 0.75rem 0; border-top: 1px solid var(--danger-border); }
        .conflict:first-of-type { border-top: 0; padding-top: 0; }
        .conflict header { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; margin-bottom: 0.4rem; }
        .conflict__fields { margin: 0.4rem 0; padding-left: 1.1rem; font-size: 0.85rem; }
        .conflict__fields li { margin-bottom: 0.35rem; }
        .merge-row label { display: block; margin-top: 0.2rem; }

        .data-panel {
            margin: 1rem 0 2rem;
            padding: 0.75rem 1rem;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 0.75rem;
        }

        .data-panel summary { cursor: pointer; font-weight: 600; list-style: none; }
        .data-panel summary::-webkit-details-marker { display: none; }
        .data-panel__body { margin-top: 0.65rem; }
        .data-panel__body p { font-size: 0.85rem; color: var(--muted); }
        .data-panel__body .form-actions { margin-top: 0.65rem; }

        .inline-form {
            margin: 0.75rem 0;
            padding: 0.85rem;
            border: 1px dashed var(--line);
            border-radius: 0.6rem;
            background: var(--surface-soft);
        }

        .inline-form h3 { margin-top: 0; }
        .inline-form label { display: block; margin-bottom: 0.55rem; font-size: 0.85rem; color: var(--muted); }
        .form-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.6rem; }
        .contact__head { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; }
        .contact__head p { margin-bottom: 0; }

        .account {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 0.75rem;
            margin-bottom: 0.75rem;
            overflow: hidden;
        }

        .account > summary {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem 0.75rem;
            align-items: center;
            padding: 0.85rem 1rem;
            cursor: pointer;
            list-style: none;
        }

        .account > summary::-webkit-details-marker { display: none; }
        .account__name { font-weight: 700; }
        .account__meta { color: var(--muted); font-size: 0.8rem; }

        .account__body { padding: 0 1rem 1rem; border-top: 1px solid var(--line); }

        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); }
        h3 { margin: 1rem 0 0.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); }
        p { margin: 0 0 0.35rem; line-height: 1.5; }

        .contact { padding: 0.4rem 0; border-bottom: 1px dashed var(--line); }
        .contact:last-child { border-bottom: 0; }

        .activity { padding: 0.75rem 0; border-bottom: 1px solid var(--line); }
        .activity:last-child { border-bottom: 0; }
        .activity--done { opacity: 0.65; }
        .activity__head { display: flex; flex-wrap: wrap; gap: 0.25rem 0.6rem; align-items: baseline; margin-bottom: 0.4rem; }

        textarea {
            width: 100%;
            min-height: 44px;
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--line);
            border-radius: 0.5rem;
            font: inherit;
            resize: vertical;
            background: var(--surface);
            color: var(--ink);
        }

        .check { display: inline-flex; align-items: center; gap: 0.4rem; margin: 0.5rem 0.75rem 0.5rem 0; font-size: 0.875rem; }
        .muted { color: var(--muted); font-size: 0.85rem; }

        .empty { padding: 2rem 1rem; text-align: center; color: var(--muted); }

        .photos { margin-top: 0.65rem; }
        .photos__grid { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .photo { position: relative; margin: 0; width: 6.5rem; }
        .photo img {
            display: block;
            width: 100%;
            height: 6.5rem;
            object-fit: cover;
            border-radius: 0.5rem;
            border: 1px solid var(--line);
            background: var(--surface);
        }
        .photo figcaption {
            margin-top: 0.2rem;
            font-size: 0.7rem;
            color: var(--muted);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .photo__remove {
            position: absolute;
            top: -0.35rem;
            right: -0.35rem;
            width: 32px;
            height: 32px;
            padding: 0;
            border-radius: 999px;
            background: var(--danger-strong);
            border-color: var(--danger-strong);
            color: #fff;
            line-height: 1;
        }
        .photo--pending img { border-style: dashed; opacity: 0.85; }
        .photo-add {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 6.5rem;
            height: 6.5rem;
            border: 1px dashed var(--line);
            border-radius: 0.5rem;
            background: var(--surface);
            color: var(--muted);
            font-size: 0.85rem;
            cursor: pointer;
        }
        .photos__count { margin-top: 0.35rem; }

        .tour {
            margin-bottom: 1rem;
            padding: 0.85rem 1rem;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 0.75rem;
        }
        .tour__head { display: flex; align-items: baseline; justify-content: space-between; gap: 0.75rem; }
        .tour__head h2 { margin: 0; font-size: 1rem; }
        .tour__list { margin: 0.6rem 0; padding: 0; list-style: none; display: grid; gap: 0.4rem; }
        .tour__item {
            display: grid;
            grid-template-columns: 1.6rem 1fr auto;
            gap: 0.5rem;
            align-items: center;
            padding: 0.35rem 0;
            border-bottom: 1px dashed var(--line);
        }
        .tour__item:last-child { border-bottom: 0; }
        .tour__index {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 999px;
            background: var(--brand);
            color: #fff;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .tour__label { display: flex; flex-direction: column; min-width: 0; }
        .tour__controls { display: flex; gap: 0.25rem; }
        .tour__routes { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 0.5rem; }

        .toast {
            position: fixed;
            left: 50%;
            bottom: 1.25rem;
            transform: translateX(-50%);
            max-width: min(90vw, 32rem);
            padding: 0.7rem 1rem;
            border-radius: 0.6rem;
            background: var(--toast-bg);
            color: var(--toast-ink);
            font-size: 0.875rem;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 40;
        }

        .toast--visible { opacity: 1; }

        @media (max-width: 899px) {
            header { padding: 0.5rem 0.65rem; gap: 0.35rem 0.5rem; }
            .brand__text { display: none; }
            .status__text { max-width: 6.5rem; }
            .only-wide { display: none; }
            .only-narrow { display: inline; }
            main { padding: 0.75rem 0.75rem 6rem; }
            button, .button, .photo-add { min-height: 44px; }
            button.small, .button.small { min-height: 44px; }
            select, input { min-height: 44px; }
            .photo__remove { width: 44px; height: 44px; top: -0.6rem; right: -0.6rem; }
            .tour__controls button { min-width: 44px; }

            .tabbar {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 30;
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 0.25rem;
                padding: 0.35rem 0.5rem calc(0.35rem + env(safe-area-inset-bottom));
                background: var(--surface);
                border-top: 1px solid var(--line);
            }

            .tab {
                position: relative;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                gap: 0.15rem;
                min-height: 52px;
                padding: 0.25rem;
                border: 0;
                border-radius: 0.6rem;
                background: transparent;
                color: var(--muted);
                font-size: 0.72rem;
                font-weight: 600;
            }

            .tab[aria-selected="true"] { color: var(--brand-dark); background: var(--pill-bg); }
            .tab svg { width: 22px; height: 22px; }

            .tab__badge {
                position: absolute;
                top: 0.15rem;
                right: calc(50% - 1.7rem);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 1.15rem;
                height: 1.15rem;
                padding: 0 0.3rem;
                border-radius: 999px;
                background: var(--brand);
                color: #fff;
                font-size: 0.65rem;
                font-weight: 700;
            }

            .tab__badge--danger { background: var(--danger-strong); }

            .toast { bottom: calc(5.5rem + env(safe-area-inset-bottom)); }
        }

        @media (min-width: 900px) {
            .tab-panel[hidden] { display: block; }
        }

        @media (prefers-reduced-motion: reduce) {
            .toast { transition: none; }
        }
    </style>
</head>
<body>
    <div class="skip-link"><a href="#main">Aller au contenu</a></div>

    <header>
        <div class="brand">
            <img src="/images/logo-mark.png" alt="" width="32" height="32">
            <span class="brand__text">Mode terrain</span>
        </div>

        <div class="toolbar">
            <span class="status">
                <span class="dot" id="status-dot" aria-hidden="true"></span>
                <span class="sr-only">État de la connexion :</span>
                <span class="status__text" id="connection">…</span>
            </span>
            <span class="status status__text" id="pending" role="status" aria-live="polite">—</span>
            <button type="button" class="icon-button" id="install-button" hidden aria-label="Installer l’application" title="Installer l’application">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>
                </svg>
            </button>
            <button type="button" class="primary" id="sync-button">
                <span class="only-wide">Synchroniser</span>
                <span class="only-narrow">Sync</span>
            </button>
            <button type="button" class="icon-button" id="theme-toggle" aria-label="Passer en mode sombre" aria-pressed="false" title="Thème clair / sombre">
                <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>
                </svg>
                <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/><path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.3 17.7-1.4 1.4"/><path d="m19.1 4.9-1.4 1.4"/>
                </svg>
            </button>
            <form method="POST" action="{{ route('filament.admin.auth.logout') }}" id="logout-form">
                @csrf
                <button type="submit" class="icon-button" aria-label="Déconnexion" title="Déconnexion">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>
                    </svg>
                </button>
            </form>
        </div>
    </header>

    <div class="banner" id="session-banner" hidden role="alert">
        Session expirée : vos modifications restent sur cet appareil.
        <a href="{{ route('filament.admin.auth.login') }}">Se reconnecter</a> pour les synchroniser.
    </div>

    <main id="main" tabindex="-1">
        <section class="tab-panel" id="panel-accounts" role="tabpanel" aria-labelledby="tab-accounts" data-tab-panel="accounts">
            <div class="install-banner" id="install-banner" hidden>
                <div>
                    <strong>Installer le mode terrain</strong>
                    <p class="muted">Accès direct depuis l’écran d’accueil et consultation hors ligne.</p>
                </div>
                <div class="form-actions">
                    <button type="button" class="primary small" id="install-prompt">Installer</button>
                    <button type="button" class="small" id="install-dismiss">Plus tard</button>
                </div>
            </div>

            <section class="conflicts" id="conflicts" aria-live="polite" hidden></section>

            <div class="summary" aria-label="Résumé">
                <span id="count">—</span>
                <span id="last-sync">Jamais synchronisé</span>
                <span id="priority-summary">—</span>
            </div>

            <label class="sr-only" for="search">Rechercher un compte, un contact ou une ville</label>
            <input
                type="search"
                class="search"
                id="search"
                placeholder="Rechercher un compte, un contact, une ville…"
                autocomplete="off"
            >

            <div class="filters">
                <label class="sr-only" for="filter-book">Filtrer par carnet</label>
                <select id="filter-book">
                    <option value="">Tous les carnets</option>
                </select>
                <label class="sr-only" for="filter-status">Filtrer par statut</label>
                <select id="filter-status">
                    <option value="">Tous les statuts</option>
                </select>
                <label class="sr-only" for="filter-follow-up">Filtrer par relance</label>
                <select id="filter-follow-up">
                    <option value="">Toutes les relances</option>
                    <option value="overdue">Relance en retard</option>
                    <option value="today">Relance aujourd’hui</option>
                    <option value="week">Relance sous 7 jours</option>
                    <option value="none">Sans relance</option>
                </select>
                <label class="sr-only" for="sort">Trier les comptes</label>
                <select id="sort">
                    <option value="priority">Priorité terrain</option>
                    <option value="follow_up">Date de relance</option>
                    <option value="name">Nom</option>
                    <option value="status">Statut</option>
                </select>
                <label class="check">
                    <input type="checkbox" id="filter-overdue">
                    Activités en retard
                </label>
            </div>

            <div id="accounts">
                <p class="empty">Chargement des données locales…</p>
            </div>
        </section>

        <section class="tab-panel" id="panel-tour" role="tabpanel" aria-labelledby="tab-tour" data-tab-panel="tour" hidden>
            <section class="tour" id="tour" aria-labelledby="tour-heading">
                <div class="tour__head">
                    <h2 id="tour-heading">Tournée du jour</h2>
                    <span class="muted" id="tour-count">0 étape(s)</span>
                </div>
                <ol class="tour__list" id="tour-list">
                    <li class="muted">Aucune étape. Ajoutez des comptes depuis l’onglet Comptes.</li>
                </ol>
                <div class="tour__routes" id="tour-routes"></div>
                <button type="button" class="small danger" id="tour-reset">Réinitialiser la tournée</button>
            </section>
        </section>

        <section class="tab-panel" id="panel-data" role="tabpanel" aria-labelledby="tab-data" data-tab-panel="data" hidden>
            <details class="data-panel" id="data-panel">
                <summary>Données locales</summary>
                <div class="data-panel__body">
                    <p id="storage-usage">Espace utilisé : calcul…</p>
                    <p id="queue-summary">Aucune opération locale en attente.</p>
                    <p id="app-version">Version hors ligne : calcul…</p>
                    <div class="form-actions">
                        <button type="button" id="export-queue">Exporter la file (JSON)</button>
                        <button type="button" class="danger" id="clear-button">Vider les données locales</button>
                    </div>
                </div>
            </details>
        </section>
    </main>

    <nav class="tabbar" id="tabbar" role="tablist" aria-label="Navigation du mode terrain">
        <button type="button" class="tab" role="tab" id="tab-accounts" aria-controls="panel-accounts" aria-selected="true" data-tab="accounts">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <span>Comptes</span>
            <span class="tab__badge tab__badge--danger" id="tab-accounts-badge" hidden></span>
        </button>
        <button type="button" class="tab" role="tab" id="tab-tour" aria-controls="panel-tour" aria-selected="false" tabindex="-1" data-tab="tour">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="6" cy="19" r="3"/><path d="M9 19h8.5a3.5 3.5 0 0 0 0-7h-11a3.5 3.5 0 0 1 0-7H15"/><circle cx="18" cy="5" r="3"/>
            </svg>
            <span>Tournée</span>
            <span class="tab__badge" id="tab-tour-badge" hidden></span>
        </button>
        <button type="button" class="tab" role="tab" id="tab-data" aria-controls="panel-data" aria-selected="false" tabindex="-1" data-tab="data">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>
            </svg>
            <span>Données</span>
            <span class="tab__badge" id="tab-data-badge" hidden></span>
        </button>
    </nav>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>

    <noscript><p class="banner">Le mode terrain nécessite JavaScript.</p></noscript>

    <script src="/js/c2me-pwa.js?v={{ filemtime(public_path('js/c2me-pwa.js')) }}" defer></script>
    <script src="/js/field-mode.js?v={{ filemtime(public_path('js/field-mode.js')) }}" defer></script>
</body>
</html>
