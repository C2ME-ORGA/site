@props(['block'])

<article class="flex h-full flex-col overflow-hidden rounded-2xl border border-mist-200 bg-white">
    <div class="flex items-start justify-between gap-4 bg-brand-500 p-6 text-white">
        <div>
            <h3 class="font-display text-xl font-extrabold leading-tight sm:text-2xl">{{ $block['title'] }}</h3>
            @if (! empty($block['subtitle']))
                <p class="mt-1 text-sm font-semibold text-white/85">{{ $block['subtitle'] }}</p>
            @endif
        </div>
        <span class="font-display text-2xl font-extrabold text-volt-300">{{ $block['number'] }}</span>
    </div>

    @if (! empty($block['text']))
        <p class="border-b border-mist-200 px-6 py-4 text-sm text-ink-700/70">{{ $block['text'] }}</p>
    @endif

    @if (! empty($block['items']))
        <dl class="flex flex-1 flex-col divide-y divide-mist-200">
            @foreach ($block['items'] as $item)
                <div class="px-6 py-4">
                    <dt class="font-bold text-ink-900">{{ $item['title'] }}</dt>
                    <dd class="mt-1 text-sm leading-relaxed text-ink-700/70">{{ $item['text'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</article>
