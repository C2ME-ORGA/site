<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title') - {{ $company['name'] ?? 'C2ME' }}</title>
    <style>
        :root {
            --brand: #0e8577;
            --ink: #111827;
            --muted: #6b7280;
            --line: #d1d5db;
            --soft: #f3f4f6;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.45;
            color: var(--ink);
            background: #f6f7f9;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 12mm auto;
            padding: 14mm 15mm;
            background: #fff;
            box-shadow: 0 4px 24px rgba(15, 23, 42, .12);
        }

        .toolbar {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-bottom: 10px;
        }

        .toolbar button {
            border: 0;
            border-radius: 6px;
            padding: 8px 14px;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
            background: var(--brand);
            color: #fff;
        }

        .toolbar button.secondary {
            background: #fff;
            color: var(--ink);
            box-shadow: inset 0 0 0 1px var(--line);
        }

        .doc-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--brand);
        }

        .doc-header img {
            width: 150px;
            height: auto;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title strong {
            display: block;
            font-size: 20px;
            letter-spacing: .02em;
            color: var(--brand);
        }

        .doc-title span {
            color: var(--muted);
            font-size: 10px;
        }

        h1 {
            margin: 0;
            font-size: 22px;
            line-height: 1.2;
        }

        h2 {
            margin: 0 0 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid var(--line);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--brand);
        }

        h3 {
            margin: 0 0 4px;
            font-size: 12px;
        }

        p { margin: 0 0 6px; }

        .identity {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            margin: 16px 0;
        }

        .identity .subtitle {
            color: var(--muted);
            font-size: 11px;
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            justify-content: flex-end;
            align-content: flex-start;
        }

        .badge {
            display: inline-block;
            border-radius: 999px;
            padding: 3px 8px;
            background: var(--soft);
            color: #374151;
            font-size: 9px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge.brand {
            background: #d1faf4;
            color: #0b6359;
        }

        .badge.danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 14px;
        }

        .panel {
            border: 1px solid var(--line);
            border-radius: 6px;
            padding: 10px 12px;
            break-inside: avoid;
        }

        .panel + .panel { margin-top: 14px; }

        dl {
            display: grid;
            grid-template-columns: 38% 62%;
            gap: 4px 10px;
            margin: 0;
        }

        dt {
            color: var(--muted);
            font-weight: 600;
        }

        dd {
            margin: 0;
            overflow-wrap: anywhere;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        th, td {
            padding: 5px 6px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }

        th {
            background: var(--soft);
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #374151;
        }

        td.right, th.right { text-align: right; }
        td.center, th.center { text-align: center; }

        .muted { color: var(--muted); }
        .strong { font-weight: 700; }
        .nowrap { white-space: nowrap; }

        .empty {
            padding: 8px 0;
            color: var(--muted);
            font-style: italic;
        }

        .notes {
            white-space: pre-line;
        }

        .rich-text p:last-child { margin-bottom: 0; }

        .doc-footer {
            margin-top: 18px;
            padding-top: 8px;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 8px;
            text-align: center;
        }

        @page {
            size: A4;
            margin: 12mm;
        }

        @media print {
            body { background: #fff; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .toolbar { display: none; }
            .panel, tr { break-inside: avoid; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar">
            <button type="button" onclick="window.print()">Imprimer</button>
            <button type="button" class="secondary" onclick="window.close()">Fermer</button>
        </div>

        <header class="doc-header">
            <img src="{{ asset($company['logo']['wide'] ?? 'images/logo-wide.png') }}" alt="{{ $company['name'] ?? 'C2ME' }}">

            <div class="doc-title">
                <strong>@yield('document-title')</strong>
                <span>Édité le {{ now()->format('d/m/Y à H:i') }}</span>
            </div>
        </header>

        @yield('content')

        <footer class="doc-footer">
            {{ $legal['legal_form'] ?? '' }} {{ $legal['legal_name'] ?? ($company['name'] ?? 'C2ME') }}
            — {{ $legal['address'] ?? '' }}
            — SIRET {{ $legal['siret'] ?? '' }}
            — TVA {{ $legal['vat'] ?? '' }}
            — RCS {{ $legal['rcs'] ?? '' }}
        </footer>
    </div>
</body>
</html>
