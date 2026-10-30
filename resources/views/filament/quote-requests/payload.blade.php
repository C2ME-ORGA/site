@php
    /** @var array<string, string> $rows */
@endphp

@if ($rows === [])
    <p class="fi-in-placeholder">Aucune information complémentaire.</p>
@else
    <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
        @foreach ($rows as $key => $value)
            <div>
                <dt class="text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::headline($key) }}</dt>
                <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $value }}</dd>
            </div>
        @endforeach
    </dl>
@endif
