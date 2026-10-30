@php
    /** @var \App\Models\AuditLog $audit */
    $subjectLabel = \App\Observers\AuditObserver::subjectOptions()[$audit->auditable_type] ?? class_basename($audit->auditable_type);
@endphp

<div class="space-y-4 text-sm">
    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Événement</dt>
            <dd class="font-medium">{{ $audit->event->getLabel() }}</dd>
        </div>

        <div>
            <dt class="text-gray-500 dark:text-gray-400">Date</dt>
            <dd class="font-medium">{{ $audit->created_at?->format('d/m/Y H:i:s') ?? '—' }}</dd>
        </div>

        <div>
            <dt class="text-gray-500 dark:text-gray-400">Élément</dt>
            <dd class="font-medium">
                {{ $subjectLabel }}@if ($audit->auditable_label) — {{ $audit->auditable_label }}@endif
            </dd>
        </div>

        <div>
            <dt class="text-gray-500 dark:text-gray-400">Auteur</dt>
            <dd class="font-medium">{{ $audit->user?->name ?? 'Système' }}</dd>
        </div>

        <div>
            <dt class="text-gray-500 dark:text-gray-400">Adresse IP</dt>
            <dd class="font-medium">{{ $audit->ip_address ?? '—' }}</dd>
        </div>
    </dl>

    @if ($audit->changes)
        <div>
            <h3 class="mb-2 font-semibold text-gray-950 dark:text-white">Changements</h3>

            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full text-left">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Champ</th>
                            <th class="px-3 py-2">Avant</th>
                            <th class="px-3 py-2">Après</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($audit->changes['old'] ?? [] as $field => $old)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $field }}</td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">{{ $old ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $audit->changes['new'][$field] ?? '—' }}</td>
                            </tr>
                        @endforeach

                        @foreach (array_diff_key($audit->changes['new'] ?? [], $audit->changes['old'] ?? []) as $field => $new)
                            <tr>
                                <td class="px-3 py-2 font-medium">{{ $field }}</td>
                                <td class="px-3 py-2 text-gray-500 dark:text-gray-400">—</td>
                                <td class="px-3 py-2">{{ $new ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
