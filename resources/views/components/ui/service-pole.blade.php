@props([
    'pole',
])

<article @class([
    'grid gap-6 border-t border-mist-200 pt-8 md:grid-cols-12 md:gap-10',
])>
    <div class="md:col-span-4">
        <p class="font-display text-sm font-extrabold tracking-[0.2em] text-brand-600">
            {{ $pole['number'] }}
        </p>
        <h3 class="mt-3 font-display text-2xl font-extrabold tracking-tight text-ink-900 sm:text-3xl">
            {{ $pole['title'] }}
        </h3>

        @if (! empty($pole['subtitle']))
            <p class="mt-3 text-sm leading-relaxed text-ink-700/70">{{ $pole['subtitle'] }}</p>
        @endif
    </div>

    <div class="md:col-span-8">
        @if (! empty($pole['text']))
            <p class="text-base leading-relaxed text-ink-700/80">{{ $pole['text'] }}</p>
        @endif

        @if (! empty($pole['items']))
            <ul class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach ($pole['items'] as $item)
                    <li class="flex gap-3 rounded-xl border border-mist-200 bg-mist-50 p-4">
                        <span class="mt-1.5 grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-500 text-white">
                            <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                            </svg>
                        </span>
                        <span class="text-sm leading-relaxed text-ink-900">{{ $item }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</article>
