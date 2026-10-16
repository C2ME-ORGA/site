@props([
    'eyebrow',
    'title',
    'lead' => null,
    'specs' => [],
])

<section class="relative overflow-hidden bg-ink-900 text-white">
    <div
        class="pointer-events-none absolute inset-0 opacity-[0.07]"
        style="background-image: linear-gradient(to right, #fff 1px, transparent 1px), linear-gradient(to bottom, #fff 1px, transparent 1px); background-size: 56px 56px;"
    ></div>
    <div class="pointer-events-none absolute -right-32 top-1/2 h-80 w-80 -translate-y-1/2 rounded-full bg-brand-500/25 blur-3xl"></div>

    <div class="container-site relative py-16 lg:py-20">
        <p class="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.2em] text-volt-400">
            <span class="inline-block h-1 w-10 rounded-full bg-volt-500"></span>
            {{ $eyebrow }}
        </p>

        <h1 class="mt-5 max-w-3xl font-display text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl">
            {{ $title }}
        </h1>

        @if ($lead)
            <p class="mt-6 max-w-3xl text-lg leading-relaxed text-mist-300/90">{{ $lead }}</p>
        @endif

        @isset($actions)
            <div class="mt-9 flex flex-wrap items-center gap-4">{{ $actions }}</div>
        @endisset

        @if (count($specs))
            <dl class="mt-10 grid max-w-3xl grid-cols-2 gap-6 border-t border-white/10 pt-7 sm:grid-cols-3">
                @foreach ($specs as $spec)
                    <div>
                        <dt class="text-[10px] font-bold uppercase tracking-[0.14em] text-mist-300/60">{{ $spec['label'] }}</dt>
                        <dd class="mt-1 font-display text-lg font-extrabold text-white sm:text-xl">{{ $spec['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>
</section>
