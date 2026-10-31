@props([
    'projects',
])

<div
    x-data="gallery()"
    class="space-y-10"
>
    {{-- Filtres --}}
    <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrer par chantier">
        <button
            type="button"
            :aria-pressed="filter === null"
            @click="filter = null"
            class="rounded-full px-4 py-2 text-sm font-bold transition"
            :class="filter === null ? 'bg-ink-900 text-white' : 'border border-mist-300 bg-white text-ink-900 hover:border-brand-400 hover:text-brand-600'"
        >
            Tous · {{ count($projects) }}
        </button>

        @foreach ($projects as $project)
            <button
                type="button"
                :aria-pressed="filter === @js($project['slug'])"
                @click="filter = @js($project['slug'])"
                :class="filter === @js($project['slug']) ? 'bg-brand-600 text-white' : 'border border-mist-300 bg-white text-ink-900 hover:border-brand-400 hover:text-brand-600'"
                class="rounded-full px-4 py-2 text-sm font-bold transition"
            >
                {{ $project['name'] }} · {{ count($project['photos']) }}
            </button>
        @endforeach
    </div>

    {{-- Grille --}}
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <template x-for="(photo, index) in visible" :key="photo.src">
            <button
                type="button"
                @click="open(index, $event)"
                class="group relative block aspect-[4/3] overflow-hidden rounded-xl bg-mist-100"
            >
                <img
                    :src="photo.thumb || photo.src"
                    :alt="photo.alt"
                    loading="lazy"
                    decoding="async"
                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                >
                <span
                    class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink-950/80 to-transparent p-3 text-left text-xs font-bold text-white opacity-0 transition group-hover:opacity-100"
                    x-text="photo.name"
                ></span>
            </button>
        </template>
    </div>

    <p
        x-cloak
        x-show="visible.length === 0"
        class="rounded-2xl border border-dashed border-mist-300 bg-mist-50 p-10 text-center text-sm text-ink-700/70"
    >
        Aucune photo dans ce chantier.
    </p>

    {{-- Visionneuse --}}
    <div
        x-cloak
        x-show="lightbox"
        x-transition.opacity
        x-ref="dialog"
        tabindex="-1"
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/95 p-4"
        @keydown.escape.window="close()"
        @keydown.arrow-left.window.prevent="prev()"
        @keydown.arrow-right.window.prevent="next()"
        role="dialog"
        aria-modal="true"
        aria-label="Visionneuse de photos"
    >
        <button
            type="button"
            x-ref="closeButton"
            @click="close()"
            class="absolute right-4 top-4 grid h-11 w-11 place-items-center rounded-full border border-white/25 text-white transition hover:bg-white/10"
            aria-label="Fermer"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>

        <button
            type="button"
            @click="prev()"
            class="absolute left-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full border border-white/25 text-white transition hover:bg-white/10"
            aria-label="Photo précédente"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <button
            type="button"
            @click="next()"
            class="absolute right-4 top-1/2 grid h-11 w-11 -translate-y-1/2 place-items-center rounded-full border border-white/25 text-white transition hover:bg-white/10"
            aria-label="Photo suivante"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <figure class="flex max-h-full max-w-4xl flex-col items-center">
            <img
                :src="current.src"
                :alt="current.alt"
                class="max-h-[75vh] w-auto rounded-lg object-contain"
            >
            <figcaption class="mt-4 text-center text-sm text-mist-300">
                <span class="font-bold text-white" x-text="current.name"></span>
                <span class="mx-2 text-mist-300/40">·</span>
                <span x-text="position"></span>
            </figcaption>
        </figure>
    </div>
</div>

@once
    <script>
        function gallery() {
            const photos = {{ Illuminate\Support\Js::from($projects) }};

            return {
                projects: photos,
                filter: null,
                lightbox: false,
                index: 0,
                trigger: null,

                init() {
                    this.$watch('filter', () => {
                        this.index = 0;
                    });
                },

                get flat() {
                    return this.projects.flatMap((project) => project.photos);
                },

                get visible() {
                    if (!this.filter) {
                        return this.flat;
                    }

                    return this.projects.find((project) => project.slug === this.filter)?.photos ?? [];
                },

                get current() {
                    return this.visible[this.index] ?? { src: '', alt: '', name: '' };
                },

                get position() {
                    return `${this.index + 1} / ${this.visible.length}`;
                },

                open(index, event) {
                    // Conserve le bouton d'origine pour y rendre le focus.
                    this.trigger = event?.currentTarget ?? null;
                    this.index = index;
                    this.lightbox = true;
                    document.body.style.overflow = 'hidden';
                    queueMicrotask(() => this.$refs.closeButton?.focus());
                },

                close() {
                    this.lightbox = false;
                    document.body.style.overflow = '';
                    queueMicrotask(() => this.trigger?.focus());
                },

                next() {
                    if (!this.lightbox) return;
                    this.index = (this.index + 1) % this.visible.length;
                },

                prev() {
                    if (!this.lightbox) return;
                    this.index = (this.index - 1 + this.visible.length) % this.visible.length;
                },
            };
        }
    </script>
@endonce
