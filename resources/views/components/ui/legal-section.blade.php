@props([
    'title',
    'id' => null,
])

{{--
    Une section des pages légales. Le corps accepte du texte riche, tableaux
    compris : c'est volontairement le seul habillage posé, pour que la mise en
    page reste identique d'une section à l'autre sans la recopier.

    scroll-mt-28 décale l'ancre sous l'en-tête collant, sinon le sommaire
    masque le titre visé.
--}}
<section @if ($id) id="{{ $id }}" @endif {{ $attributes->class(['scroll-mt-28']) }}>
    <h2 class="font-display text-2xl font-extrabold tracking-tight text-ink-900 sm:text-3xl">
        {{ $title }}
    </h2>

    <div class="mt-5 space-y-5 text-[15px] leading-relaxed text-ink-700/75">
        {{ $slot }}
    </div>
</section>
