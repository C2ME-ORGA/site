@extends('print.layout')

@section('title', 'Fiche affaire - '.$opportunity->reference)
@section('document-title', 'Fiche affaire')

@php
    $source = $opportunity->source instanceof \App\Enums\CrmSource
        ? $opportunity->source
        : \App\Enums\CrmSource::tryFrom((string) $opportunity->source);
    $description = str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $opportunity->description ?? '');
    $description = trim(html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
@endphp

@section('content')
    <section class="identity">
        <div>
            <h1>{{ $opportunity->title }}</h1>
            <div class="subtitle">{{ $opportunity->reference }}</div>
        </div>

        <div class="badges">
            <span class="badge brand">{{ $opportunity->stage->getLabel() }}</span>
            @if ($opportunity->probability !== null)
                <span class="badge">{{ $opportunity->probability }} %</span>
            @endif
            @if ($source)
                <span class="badge">Source : {{ $source->getLabel() }}</span>
            @endif
        </div>
    </section>

    <div class="grid">
        <section class="panel">
            <h2>Affaire</h2>
            <dl>
                <dt>Référence</dt>
                <dd>{{ $opportunity->reference }}</dd>
                <dt>Étape</dt>
                <dd>{{ $opportunity->stage->getLabel() }}</dd>
                <dt>Montant estimé</dt>
                <dd>{{ $opportunity->amount_estimated !== null ? number_format((float) $opportunity->amount_estimated, 2, ',', ' ').' €' : '—' }}</dd>
                <dt>Probabilité</dt>
                <dd>{{ $opportunity->probability !== null ? $opportunity->probability.' %' : '—' }}</dd>
                <dt>Clôture prévue</dt>
                <dd>{{ $opportunity->expected_close_date?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Commercial</dt>
                <dd>{{ $opportunity->owner?->name ?? '—' }}</dd>
                <dt>Créée le</dt>
                <dd>{{ $opportunity->created_at?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Gagnée le</dt>
                <dd>{{ $opportunity->won_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                <dt>Clôturée le</dt>
                <dd>{{ $opportunity->closed_at?->format('d/m/Y H:i') ?? '—' }}</dd>
            </dl>
        </section>

        <section class="panel">
            <h2>Compte</h2>
            <dl>
                <dt>Société</dt>
                <dd class="strong">{{ $opportunity->account?->name ?? '—' }}</dd>
                <dt>Raison sociale</dt>
                <dd>{{ $opportunity->account?->legal_name ?? '—' }}</dd>
                <dt>Carnet</dt>
                <dd>{{ $opportunity->account?->book->getLabel() ?? '—' }}</dd>
                <dt>Adresse</dt>
                <dd>
                    @if ($opportunity->account)
                        {{ $opportunity->account->address_line1 ?: '—' }}<br>
                        @if ($opportunity->account->address_line2)
                            {{ $opportunity->account->address_line2 }}<br>
                        @endif
                        {{ trim(($opportunity->account->postal_code ?? '').' '.($opportunity->account->city ?? '')) ?: '—' }}
                    @else
                        —
                    @endif
                </dd>
                <dt>Contact</dt>
                <dd>
                    {{ $opportunity->contact?->fullName() ?? '—' }}
                    @if ($opportunity->contact?->job_title)
                        <br><span class="muted">{{ $opportunity->contact->job_title }}</span>
                    @endif
                </dd>
                <dt>E-mail contact</dt>
                <dd>{{ $opportunity->contact?->email ?? '—' }}</dd>
                <dt>Téléphone contact</dt>
                <dd>{{ $opportunity->contact?->phone ?: ($opportunity->contact?->mobile ?? '—') }}</dd>
            </dl>
        </section>
    </div>

    @if (filled($description))
        <section class="panel">
            <h2>Description</h2>
            <p class="notes">{{ $description }}</p>
        </section>
    @endif

    @if (filled($opportunity->lost_reason))
        <section class="panel">
            <h2>Motif de perte</h2>
            <p class="notes">{{ $opportunity->lost_reason }}</p>
        </section>
    @endif

    <section class="panel">
        <h2>Devis liés ({{ $opportunity->quotes->count() }})</h2>

        @if ($opportunity->quotes->isEmpty())
            <p class="empty">Aucun devis lié.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Statut</th>
                        <th class="right">Montant HT</th>
                        <th>Émis le</th>
                        <th>Valide jusqu'au</th>
                        <th>Accepté le</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($opportunity->quotes as $quote)
                        <tr>
                            <td class="nowrap">{{ $quote->reference }}</td>
                            <td>{{ $quote->status->getLabel() }}</td>
                            <td class="right">{{ $quote->amount_ht !== null ? number_format((float) $quote->amount_ht, 2, ',', ' ').' €' : '—' }}</td>
                            <td>{{ $quote->issued_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $quote->valid_until?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $quote->accepted_at?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    @if ($opportunity->activities->isNotEmpty())
        <section class="panel">
            <h2>Dernières activités</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Sujet</th>
                        <th>Par</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($opportunity->activities as $activity)
                        <tr>
                            <td class="nowrap">{{ $activity->occurred_at?->format('d/m/Y') ?? $activity->due_at?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $activity->type->getLabel() }}</td>
                            <td>{{ $activity->subject }}</td>
                            <td>{{ $activity->user?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
@endsection
