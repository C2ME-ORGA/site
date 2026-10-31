@props([
    /**
     * Correspondance champ d’erreur => id de l’élément à cibler.
     *
     * @var array<string, string>
     */
    'fields' => [],
])

@if ($errors->any())
    <div
        role="alert"
        class="rounded-xl border border-red-300 bg-red-50 px-5 py-4"
    >
        <p class="text-sm font-bold text-red-700">
            Le formulaire contient des erreurs. Merci de corriger les champs suivants :
        </p>

        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">
            @foreach ($fields as $field => $target)
                @if ($errors->has($field))
                    <li>
                        <a href="#{{ $target }}" class="font-semibold underline underline-offset-2">
                            {{ $errors->first($field) }}
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
@endif
