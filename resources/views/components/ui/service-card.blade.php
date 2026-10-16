@props(['service'])

<article class="group flex h-full flex-col overflow-hidden rounded-2xl border border-mist-200 bg-white transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-xl">
    {{-- En-tête --}}
    <div class="relative bg-ink-900 p-6 text-white">
        <span class="absolute right-5 top-5 font-display text-sm font-extrabold text-volt-500">{{ $service['number'] }}</span>
        <span class="inline-block h-1 w-10 rounded-full bg-volt-500"></span>
        <h3 class="mt-4 font-display text-2xl font-extrabold leading-tight">{{ $service['name'] }}</h3>
        <p class="mt-3 text-sm leading-relaxed text-mist-300/90">{{ $service['short'] }}</p>
    </div>

    {{-- Spécifications --}}
    <dl class="grid grid-cols-3 border-b border-mist-200 bg-mist-100">
        @foreach ($service['specs'] as $spec)
            <div class="border-r border-mist-200 px-3 py-4 text-center last:border-r-0">
                <dt class="text-[10px] font-bold uppercase tracking-[0.12em] text-brand-600">{{ $spec['label'] }}</dt>
                <dd class="mt-1 font-display text-sm font-extrabold text-ink-900">{{ $spec['value'] }}</dd>
            </div>
        @endforeach
    </dl>

    {{-- Points clés --}}
    <ul class="flex flex-1 flex-col gap-3 p-6">
        @foreach (array_slice($service['features'], 0, 3) as $feature)
            <li class="flex gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                </svg>
                <span class="text-sm text-ink-800">
                    <span class="font-bold">{{ $feature['title'] }} — </span>{{ $feature['text'] }}
                </span>
            </li>
        @endforeach
    </ul>

    {{-- Lien --}}
    <div class="border-t border-mist-200 p-5">
        <a
            href="{{ route($service['route']) }}"
            class="inline-flex items-center gap-2 text-sm font-bold text-brand-600 transition group-hover:gap-3"
        >
            Découvrir ce service
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 10h12m0 0-4-4m4 4-4 4" />
            </svg>
        </a>
    </div>
</article>
