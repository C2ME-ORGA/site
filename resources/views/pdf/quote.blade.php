<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 30px 35px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        .header { width: 100%; }
        .header td { vertical-align: top; }
        .company-name { font-size: 14px; font-weight: bold; }
        .logo { text-align: right; font-size: 22px; font-weight: bold; color: #0e8577; letter-spacing: 1px; }
        .logo small { display: block; font-size: 6px; letter-spacing: 0.5px; color: #555; }
        .devis-box { border: 1px solid #000; border-radius: 8px; margin-top: 18px; width: 55%; }
        .devis-box table { width: 100%; border-collapse: collapse; }
        .devis-box td { border: 1px solid #000; padding: 5px 6px; }
        .devis-title { font-size: 16px; font-weight: bold; font-style: italic; }
        .num-label { font-weight: bold; text-align: center; }
        .num-value { text-align: center; font-weight: bold; }
        .client { margin-top: -55px; margin-left: 58%; font-size: 10px; }
        .reference { margin-top: 22px; font-size: 10px; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.lines th { background: #e6e6e6; border: 1px solid #999; padding: 4px; font-size: 9px; }
        table.lines td { border: 1px solid #999; padding: 4px; vertical-align: top; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .totals { width: 40%; margin-left: 60%; margin-top: 10px; }
        .totals td { border: 1px solid #999; padding: 4px 6px; }
        .bank { margin-top: 14px; font-size: 9px; }
        .signature { margin-top: 26px; font-size: 10px; font-weight: bold; }
        .footer { position: fixed; bottom: 10px; left: 35px; right: 35px; border-top: 1px solid #999; padding-top: 4px; font-size: 6.5px; color: #555; text-align: center; }
        .legal { margin-top: 10px; font-size: 7px; color: #444; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="60%">
                <div class="company-name">{{ $legal['legal_form'] ?? '' }} {{ $legal['legal_name'] ?? $company['name'] }}</div>
                <div>{{ $company['locations'][0]['line1'] ?? '' }}</div>
                <div>{{ ($company['locations'][0]['postal_code'] ?? '').' '.($company['locations'][0]['city'] ?? '') }}</div>
                <div>Tél mobile : {{ $company['mobile'] ?? '' }}</div>
                <div>Email : {{ $company['email'] ?? '' }}</div>
            </td>
            <td width="40%" class="logo">
                C2ME
                <small>CHARPENTE MÉTALLIQUE · DÉCOUPE · ÉTUDES</small>
            </td>
        </tr>
    </table>

    <table class="devis-box">
        <tr>
            <td class="devis-title">Devis</td>
            <td class="num-label">Numéro</td>
        </tr>
        <tr>
            <td>Date : {{ optional($quote->issued_at)->format('d/m/Y') ?? now()->format('d/m/Y') }}</td>
            <td class="num-value">{{ $quote->reference }}</td>
        </tr>
        <tr>
            <td class="center bold">Code client</td>
            <td class="center bold">Date de validité</td>
        </tr>
        <tr>
            <td class="center">{{ $quote->account->code ?? $quote->account->name }}</td>
            <td class="center">{{ optional($quote->valid_until)->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="center bold">Mode de règlement</td>
            <td class="center">Virement</td>
        </tr>
        <tr>
            <td colspan="2" class="center">N° de TVA Intracom : {{ $quote->account->vat_number ?? '—' }}</td>
        </tr>
    </table>

    <div class="client">
        <div class="bold">{{ $quote->account->legal_name ?? $quote->account->name }}</div>
        <div>{{ $quote->account->address_line1 }}</div>
        <div>{{ $quote->account->address_line2 }}</div>
        <div>{{ trim(($quote->account->postal_code ?? '').' '.($quote->account->city ?? '')) }}</div>
        <div>{{ $quote->account->country }}</div>
    </div>

    <div class="reference"><span class="bold">Référence :</span> {{ $quote->subject }}</div>

    <table class="lines">
        <thead>
            <tr>
                <th width="7%">Code</th>
                <th>Description</th>
                <th width="8%">Qté</th>
                <th width="9%">Unité</th>
                <th width="12%">P.U. HT</th>
                <th width="8%">% Rem</th>
                <th width="13%">Montant HT</th>
                <th width="8%">TVA</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($quote->lines as $line)
                <tr>
                    <td class="center">{{ $line->code }}</td>
                    <td>
                        <div class="bold">{{ $line->designation }}</div>
                        @if (filled($line->description))
                            <div>{!! nl2br(e($line->description)) !!}</div>
                        @endif
                    </td>
                    <td class="right">{{ number_format((float) $line->quantity, 2, ',', ' ') }}</td>
                    <td class="center">{{ config('crm.quotes.units.'.$line->unit, $line->unit) }}</td>
                    <td class="right">{{ number_format((float) $line->unit_price, 2, ',', ' ') }}</td>
                    <td class="right">{{ number_format((float) $line->discount_percent, 2, ',', ' ') }}</td>
                    <td class="right">{{ number_format((float) $line->total_ht, 2, ',', ' ') }}</td>
                    <td class="right">{{ number_format((float) $quote->vat_rate, 2, ',', ' ') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="center">Aucune ligne.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="bold">Total HT</td>
            <td class="right">{{ number_format((float) $quote->amount_ht, 2, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="bold">Total TVA</td>
            <td class="right">{{ number_format((float) $quote->amount_ttc - (float) $quote->amount_ht, 2, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="bold">Total TTC</td>
            <td class="right">{{ number_format((float) $quote->amount_ttc, 2, ',', ' ') }}</td>
        </tr>
        <tr>
            <td class="bold">Net à payer</td>
            <td class="right bold">{{ number_format((float) $quote->amount_ttc, 2, ',', ' ') }} €</td>
        </tr>
    </table>

    <div class="bank">
        <div class="bold">Coordonnées bancaires société :</div>
        <div>Banque : {{ config('crm.bank.name', '—') }}</div>
        <div>RIB : {{ config('crm.bank.rib', '—') }}</div>
        <div>IBAN : {{ config('crm.bank.iban', '—') }}</div>
        <div>BIC : {{ config('crm.bank.bic', '—') }}</div>
    </div>

    <div class="signature">
        Pour le client (signature précédée de la mention : Lu et approuvé,<br>bon pour accord)
    </div>

    <div class="legal">
        Devis gratuit. Les prix TTC sont établis sur la base des taux de TVA en vigueur à la date de remise de l'offre.
        Toute variation de ces taux sera répercutée sur les prix.<br>
        Autoliquidation, TVA due par le preneur. Article 283-2 nonies du CGI.
    </div>

    <div class="footer">
        Siret : {{ $legal['siret'] ?? '' }} - APE : {{ $legal['ape'] ?? '' }} - RCS : {{ $legal['rcs'] ?? '' }}
        {{ $legal['siren'] ?? '' }} - N° TVA intracom : {{ $legal['vat'] ?? '' }} - Capital : {{ $legal['capital'] ?? '' }}
    </div>
</body>
</html>
