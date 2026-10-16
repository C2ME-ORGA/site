@props([
    'title',
    'text' => null,
    'primaryLabel' => 'Demander un devis',
    'primaryHref' => null,
    'secondaryLabel' => null,
    'secondaryHref' => null,
])

<section class="bg-ink-900 py-20 lg:py-24">
    <div class="container-site flex flex-col items-start gap-8 lg:flex-row lg:items-center lg:justify-between">
        <div class="max-w-2xl">
            <h2 class="font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
                {{ $title }}
            </h2>

            @if ($text)
                <p class="mt-4 text-base leading-relaxed text-mist-300/70">{{ $text }}</p>
            @endif
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-4">
            <x-ui.button :href="$primaryHref ?? route('quote')" variant="volt" size="lg">
                {{ $primaryLabel }}
            </x-ui.button>

            @if ($secondaryLabel)
                <x-ui.button :href="$secondaryHref" variant="ghost-light" size="lg">
                    {{ $secondaryLabel }}
                </x-ui.button>
            @endif
        </div>
    </div>
</section>
