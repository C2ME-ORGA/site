@props(['photos'])

@if ($photos !== [])
    <div
        x-data="projectLightbox({{ Illuminate\Support\Js::from($photos) }})"
        @keydown.escape.window="close()"
        @keydown.arrow-left.window.prevent="prev()"
        @keydown.arrow-right.window.prevent="next()"
    >
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($photos as $index => $photo)
                <button
                    type="button"
                    @click="open({{ $index }}, $event)"
                    class="group relative aspect-[4/3] overflow-hidden rounded-xl bg-mist-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 focus-visible:ring-offset-2"
                >
                    <img
                        src="{{ $photo['thumb'] }}"
                        alt="{{ $photo['alt'] }}"
                        loading="lazy"
                        decoding="async"
                        class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                    >
                </button>
            @endforeach
        </div>

        <div
            x-cloak
            x-show="lightbox"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-ink-950/95 p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Visionneuse de photos du chantier"
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
                    <span class="font-bold text-white" x-text="current.caption"></span>
                    <span class="mx-2 text-mist-300/40">·</span>
                    <span x-text="position"></span>
                </figcaption>
            </figure>
        </div>
    </div>

    @once
        <script>
            function projectLightbox(photos) {
                return {
                    photos,
                    lightbox: false,
                    index: 0,
                    trigger: null,

                    get current() {
                        return this.photos[this.index] ?? { src: '', alt: '', caption: '' };
                    },

                    get position() {
                        return `${this.index + 1} / ${this.photos.length}`;
                    },

                    open(index, event) {
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
                        if (!this.lightbox) {
                            return;
                        }

                        this.index = (this.index + 1) % this.photos.length;
                    },

                    prev() {
                        if (!this.lightbox) {
                            return;
                        }

                        this.index = (this.index - 1 + this.photos.length) % this.photos.length;
                    },
                };
            }
        </script>
    @endonce
@endif
