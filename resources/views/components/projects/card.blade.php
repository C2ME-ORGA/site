@props(['project'])

@php
    $cover = $project->cover();
    $meta = collect([$project->location, $project->year])->filter()->join(' · ');
@endphp

<a
    href="{{ $project->url() }}"
    class="group flex h-full flex-col overflow-hidden rounded-2xl border border-mist-200 bg-white transition hover:-translate-y-1 hover:border-brand-200 hover:shadow-xl"
>
    <div class="relative aspect-[4/3] overflow-hidden bg-mist-100">
        @if ($cover)
            <img
                src="{{ $cover->thumbUrl() }}"
                alt="{{ $cover->alt ?: 'Réalisation C2ME — '.$project->title }}"
                loading="lazy"
                decoding="async"
                class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
            >
        @else
            <div class="grid h-full place-items-center text-mist-300">
                <svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A1.5 1.5 0 0 0 21.75 19.5V4.5A1.5 1.5 0 0 0 20.25 3H3.75A1.5 1.5 0 0 0 2.25 4.5v15A1.5 1.5 0 0 0 3.75 21Z" />
                </svg>
            </div>
        @endif

        @if ($project->category)
            <span class="absolute left-3 top-3 rounded-full bg-ink-950/80 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.08em] text-white">
                {{ $project->category->name }}
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-3 p-5">
        <h3 class="font-display text-lg font-extrabold leading-tight text-ink-900">
            {{ $project->title }}
        </h3>

        @if ($meta !== '')
            <p class="text-sm text-ink-700/70">{{ $meta }}</p>
        @endif

        @if (filled($project->services))
            <ul class="flex flex-wrap gap-1.5">
                @foreach (array_slice($project->services, 0, 3) as $service)
                    <li class="rounded-full bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700">
                        {{ $service }}
                    </li>
                @endforeach
            </ul>
        @endif

        <span class="mt-auto inline-flex items-center gap-2 pt-2 text-sm font-bold text-brand-600 transition group-hover:gap-3">
            Voir le chantier
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.19L10.7 6.03a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.24-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
            </svg>
            <span class="sr-only">— {{ $project->title }}</span>
        </span>
    </div>
</a>
