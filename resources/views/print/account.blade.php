@extends('print.layout')

@section('title', 'Fiche compte - '.$account->name)
@section('document-title', 'Fiche compte')

@section('content')
    <section class="identity">
        <div>
            <h1>{{ $account->name }}</h1>
            <div class="subtitle">
                {{ $account->legal_name ?: '—' }}
                @if ($account->code)
                    · Code {{ $account->code }}
                @endif
            </div>
        </div>

        <div class="badges">
            <span class="badge brand">{{ $account->book->shortLabel() }}</span>
            <span class="badge">{{ $account->category->getLabel() }}</span>
            <span class="badge">{{ $account->status->getLabel() }}</span>
            @if ($account->potential)
                <span class="badge">Potentiel : {{ $account->potential->getLabel() }}</span>
            @endif
            @if ($account->source)
                <span class="badge">Source : {{ $account->source->getLabel() }}</span>
            @endif
        </div>
    </section>

    <div class="grid">
        <section class="panel">
            <h2>Coordonnées</h2>
            <dl>
                <dt>Adresse</dt>
                <dd>
                    {{ $account->address_line1 ?: '—' }}<br>
                    @if ($account->address_line2)
                        {{ $account->address_line2 }}<br>
                    @endif
                    {{ trim(($account->postal_code ?? '').' '.($account->city ?? '')) ?: '—' }}<br>
                    {{ $account->country ?: '—' }}
                </dd>
                <dt>Téléphone</dt>
                <dd>{{ $account->phone ?: '—' }}</dd>
                <dt>Mobile</dt>
                <dd>{{ $account->mobile ?: '—' }}</dd>
                <dt>E-mail</dt>
                <dd>{{ $account->email ?: '—' }}</dd>
                <dt>Site web</dt>
                <dd>{{ $account->website ?: '—' }}</dd>
                <dt>SIRET</dt>
                <dd>{{ $account->siret ?: '—' }}</dd>
                <dt>N° TVA</dt>
                <dd>{{ $account->vat_number ?: '—' }}</dd>
            </dl>
        </section>

        <section class="panel">
            <h2>Suivi commercial</h2>
            <dl>
                <dt>Commercial</dt>
                <dd>{{ $account->owner?->name ?? '—' }}</dd>
                <dt>Dernier contact</dt>
                <dd>{{ $account->last_contacted_at?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Prochaine relance</dt>
                <dd>{{ $account->next_follow_up_at?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Créé le</dt>
                <dd>{{ $account->created_at?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Dernière mise à jour</dt>
                <dd>{{ $account->updated_at?->format('d/m/Y') ?? '—' }}</dd>
                <dt>Étiquettes</dt>
                <dd>
                    @forelse ($account->tags as $tag)
                        <span class="badge" style="color: {{ \App\Enums\CrmTagColor::tryFrom((string) $tag->color)?->printTint() ?? '#6b7280' }};">{{ $tag->name }}</span>
                    @empty
                        —
                    @endforelse
                </dd>
            </dl>
        </section>
    </div>

    @if ($metadata !== [])
        <section class="panel">
            <h2>Informations spécifiques — {{ $account->book->shortLabel() }}</h2>
            <dl>
                @foreach ($metadata as $field)
                    <dt>{{ $field['label'] }}</dt>
                    <dd>{{ $field['value'] ?: '—' }}</dd>
                @endforeach
            </dl>
        </section>
    @endif

    <section class="panel">
        <h2>Contacts ({{ $account->contacts->count() }})</h2>

        @if ($account->contacts->isEmpty())
            <p class="empty">Aucun contact enregistré.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Fonction</th>
                        <th>E-mail</th>
                        <th>Téléphone</th>
                        <th>Mobile</th>
                        <th class="center">Principal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($account->contacts as $contact)
                        <tr>
                            <td class="strong">{{ $contact->fullName() }}</td>
                            <td>{{ $contact->job_title ?: '—' }}</td>
                            <td>{{ $contact->email ?: '—' }}</td>
                            <td>{{ $contact->phone ?: '—' }}</td>
                            <td>{{ $contact->mobile ?: '—' }}</td>
                            <td class="center">{{ $contact->is_primary ? 'Oui' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="panel">
        <h2>Affaires ({{ $account->opportunities->count() }})</h2>

        @if ($account->opportunities->isEmpty())
            <p class="empty">Aucune affaire enregistrée.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Intitulé</th>
                        <th>Étape</th>
                        <th class="right">Montant estimé</th>
                        <th class="right">Probabilité</th>
                        <th>Clôture prévue</th>
                        <th>Commercial</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($account->opportunities as $opportunity)
                        <tr>
                            <td class="nowrap">{{ $opportunity->reference }}</td>
                            <td>{{ $opportunity->title }}</td>
                            <td>{{ $opportunity->stage->getLabel() }}</td>
                            <td class="right">{{ $opportunity->amount_estimated !== null ? number_format((float) $opportunity->amount_estimated, 2, ',', ' ').' €' : '—' }}</td>
                            <td class="right">{{ $opportunity->probability !== null ? $opportunity->probability.' %' : '—' }}</td>
                            <td>{{ $opportunity->expected_close_date?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $opportunity->owner?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section class="panel">
        <h2>Devis ({{ $account->quotes->count() }})</h2>

        @if ($account->quotes->isEmpty())
            <p class="empty">Aucun devis enregistré.</p>
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
                    @foreach ($account->quotes as $quote)
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

    @if ($account->activities->isNotEmpty())
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
                    @foreach ($account->activities as $activity)
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

    @if (filled($account->notes))
        <section class="panel">
            <h2>Notes</h2>
            <p class="notes">{{ $account->notes }}</p>
        </section>
    @endif
@endsection
