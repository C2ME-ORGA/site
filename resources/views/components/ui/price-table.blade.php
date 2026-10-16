@props([
    'rows',
])

<div class="overflow-hidden rounded-2xl border border-mist-200">
    <table class="w-full border-collapse text-left">
        <thead>
            <tr class="bg-ink-900 text-white">
                <th scope="col" class="px-5 py-4 text-xs font-bold uppercase tracking-[0.12em] sm:px-7">
                    Quantité commandée
                </th>
                <th scope="col" class="px-5 py-4 text-right text-xs font-bold uppercase tracking-[0.12em] sm:px-7">
                    Tarif HT / kg
                </th>
            </tr>
        </thead>
        <tbody class="divide-y divide-mist-200 bg-white">
            @foreach ($rows as $index => [$quantity, $price])
                <tr @class([
                    'transition-colors hover:bg-brand-50/60',
                    'bg-mist-50' => $index % 2 === 1,
                ])>
                    <td class="px-5 py-4 text-sm text-ink-900 sm:px-7 sm:text-base">{{ $quantity }}</td>
                    <td class="px-5 py-4 text-right sm:px-7">
                        <span @class([
                            'font-display font-extrabold',
                            'text-brand-600' => $price === 'Sur demande',
                            'text-ink-900' => $price !== 'Sur demande',
                        ])>
                            {{ $price }}
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
