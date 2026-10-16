@props(['pricing'])

<div class="overflow-hidden rounded-2xl border border-mist-200 bg-white">
    <div class="flex flex-wrap items-baseline justify-between gap-3 border-b border-mist-200 bg-ink-900 px-6 py-5 text-white">
        <h2 class="font-display text-xl font-extrabold sm:text-2xl">{{ $pricing['title'] }}</h2>
        @if (! empty($pricing['caption']))
            <p class="text-xs text-mist-300/70">{{ $pricing['caption'] }}</p>
        @endif
    </div>

    <table class="w-full text-left text-sm">
        <thead>
            <tr class="bg-mist-100 text-[11px] uppercase tracking-[0.1em] text-ink-700/70">
                <th class="px-5 py-3 font-bold sm:px-6">{{ $pricing['columns'][0] }}</th>
                <th class="px-5 py-3 text-right font-bold sm:px-6">{{ $pricing['columns'][1] }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-mist-200">
            @foreach ($pricing['rows'] as $index => $row)
                <tr class="transition odd:bg-white even:bg-mist-50 hover:bg-brand-50">
                    <td class="px-5 py-3.5 text-ink-800 sm:px-6">{{ $row[0] }}</td>
                    <td @class([
                        'px-5 py-3.5 text-right font-bold sm:px-6',
                        'text-brand-600' => $index === 0,
                        'text-ink-900' => $index !== 0,
                    ])>
                        {{ $row[1] }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (! empty($pricing['materials']))
        <div class="grid gap-4 border-t border-mist-200 bg-mist-100 p-5 sm:grid-cols-2 sm:p-6">
            @foreach ($pricing['materials'] as $material)
                <div class="rounded-lg border-l-4 border-brand-500 bg-white px-5 py-4">
                    <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-ink-700/70">{{ $material['label'] }}</p>
                    <p class="mt-1 font-display text-lg font-extrabold text-ink-900">{{ $material['value'] }}</p>
                </div>
            @endforeach
        </div>
    @endif
</div>
