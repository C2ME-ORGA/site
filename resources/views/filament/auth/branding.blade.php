{{-- Signature visuelle des pages de connexion C2ME, posée sur la photo. --}}
<div class="flex h-full w-full flex-col items-start justify-end gap-4">
    <div class="rounded-xl bg-white/95 px-4 py-3 shadow-lg ring-1 ring-black/5">
        <img
            src="{{ asset('images/logo-wide.png') }}"
            alt="C2ME — Construire ensemble, un bâtiment à votre image"
            class="h-9 w-auto"
        >
    </div>

    @isset($label)
        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-white/90">
            {{ $label }}
        </p>
    @endisset
</div>
