@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
])

@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2';

    $variants = [
        'primary' => 'bg-brand-500 text-white hover:bg-brand-600',
        'dark' => 'bg-ink-900 text-white hover:bg-ink-800',
        'volt' => 'bg-volt-500 text-ink-950 hover:bg-volt-400',
        'outline' => 'border border-ink-900/15 bg-white text-ink-900 hover:border-brand-400 hover:text-brand-600',
        'ghost-light' => 'border border-white/25 text-white hover:bg-white/10',
    ];

    $sizes = [
        'md' => 'px-5 py-3 text-sm',
        'lg' => 'px-6 py-3.5 text-base',
    ];

    $classes = $base.' '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif
