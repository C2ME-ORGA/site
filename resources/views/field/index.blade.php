<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0e8577">
    <title>Mode terrain — C2ME</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/images/logo-mark.png" type="image/png">
    <style>
        :root {
            --brand: #0e8577;
            --brand-dark: #0b6d6d;
            --ink: #0d2b33;
            --muted: #64748b;
            --line: #e2e8f0;
            --bg: #f8fafc;
            --warn: #b45309;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            font-size: 15px;
        }

        header {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: #fff;
            border-bottom: 1px solid var(--line);
        }

        .brand { display: flex; align-items: center; gap: 0.6rem; font-weight: 700; }
        .brand img { width: 36px; height: 36px; }

        .toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; }

        .status { display: flex; align-items: center; gap: 0.4rem; color: var(--muted); font-size: 0.85rem; }
        .dot { width: 0.55rem; height: 0.55rem; border-radius: 999px; background: #16a34a; }
        .dot--off { background: var(--warn); }

        button, .button {
            appearance: none;
            border: 1px solid var(--line);
            background: #fff;
            color: var(--ink);
            border-radius: 0.5rem;
            padding: 0.5rem 0.85rem;
            font: inherit;
            font-size: 0.875rem;
            cursor: pointer;
            text-decoration: none;
        }

        button.primary { background: var(--brand); border-color: var(--brand); color: #fff; }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        button.danger { color: #b91c1c; }

        main { max-width: 60rem; margin: 0 auto; padding: 1rem; }

        .summary {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1.5rem;
            padding: 0.85rem 1rem;
            margin-bottom: 1rem;
            background: #fff;
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
            background: #fff;
        }

        .account {
            background: #fff;
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

        .pill {
            display: inline-block;
            padding: 0.1rem 0.5rem;
            border-radius: 999px;
            background: #e7f7f7;
            color: var(--brand-dark);
            font-size: 0.75rem;
            font-weight: 600;
        }

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
            padding: 0.6rem 0.75rem;
            border: 1px solid var(--line);
            border-radius: 0.5rem;
            font: inherit;
            resize: vertical;
            background: #fff;
        }

        .check { display: inline-flex; align-items: center; gap: 0.4rem; margin: 0.5rem 0.75rem 0.5rem 0; font-size: 0.875rem; }
        .muted { color: var(--muted); font-size: 0.85rem; }

        .empty { padding: 2rem 1rem; text-align: center; color: var(--muted); }

        .toast {
            position: fixed;
            left: 50%;
            bottom: 1.25rem;
            transform: translateX(-50%);
            max-width: 90vw;
            padding: 0.7rem 1rem;
            border-radius: 0.6rem;
            background: var(--ink);
            color: #fff;
            font-size: 0.875rem;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }

        .toast--visible { opacity: 1; }

        @media (prefers-color-scheme: dark) {
            :root { --bg: #0b1418; --ink: #e2e8f0; --line: #1e2f36; }
            body { background: var(--bg); }
            header, .summary, .account, .search, textarea { background: #102229; }
            .pill { background: #0b3b39; color: #7fe0d6; }
            .toast { background: #e2e8f0; color: #0b1418; }
        }
    </style>
</head>
<body>
    <header>
        <div class="brand">
            <img src="/images/logo-mark.png" alt="" width="36" height="36">
            <span>Mode terrain</span>
        </div>

        <div class="toolbar">
            <span class="status">
                <span class="dot" id="status-dot"></span>
                <span id="connection">…</span>
            </span>
            <span class="status" id="pending">—</span>
            <button type="button" class="primary" id="sync-button">Synchroniser</button>
            <button type="button" id="clear-button">Effacer les données</button>
            <form method="POST" action="{{ route('filament.admin.auth.logout') }}" id="logout-form">
                @csrf
                <button type="submit">Déconnexion</button>
            </form>
        </div>
    </header>

    <main>
        <div class="summary">
            <span id="count">—</span>
            <span id="last-sync">Jamais synchronisé</span>
        </div>

        <input
            type="search"
            class="search"
            id="search"
            placeholder="Rechercher un compte, un contact, une ville…"
            autocomplete="off"
        >

        <div id="accounts">
            <p class="empty">Chargement des données locales…</p>
        </div>
    </main>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>

    <script src="/js/c2me-pwa.js" defer></script>
    <script src="/js/field-mode.js" defer></script>
</body>
</html>
