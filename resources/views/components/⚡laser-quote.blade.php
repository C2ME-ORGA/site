<?php

use App\Enums\QuoteKind;
use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use App\Models\QuoteRequestFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $step = 1;

    /** Étape 1 — matière et volume. */
    public string $material = '';

    public ?int $thickness = null;

    public string $quantity = '';

    /** Étape 2 — détails techniques. */
    public string $complexity = '';

    public ?int $length = null;

    public ?int $width = null;

    public string $delay = 'standard';

    public string $delivery = 'retrait';

    public string $city = '';

    /** Étape 3 — plans. */
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $plans = [];

    public bool $plansLater = false;

    /** Étape 4 — coordonnées. */
    public string $name = '';

    public string $company = '';

    public string $email = '';

    public string $phone = '';

    public string $message = '';

    /** Pot de miel : doit rester vide. */
    public string $website = '';

    public bool $submitted = false;

    public static function totalSteps(): int
    {
        return 4;
    }

    /**
     * Matières découpables sur la machine, lues dans la configuration.
     *
     * @return list<array<string, mixed>>
     */
    public static function materials(): array
    {
        return config('site.quote.laser.materials', []);
    }

    /**
     * Épaisseurs disponibles pour la matière sélectionnée.
     *
     * @return list<int>
     */
    public function availableThicknesses(): array
    {
        $material = collect(self::materials())
            ->firstWhere('value', $this->material);

        return $material['thicknesses'] ?? [];
    }

    public function updatedMaterial(): void
    {
        // L'épaisseur dépend de la matière : une valeur choisie pour
        // l'ancienne matière n'a plus de sens.
        $this->thickness = null;
    }

    /**
     * Libellé lisible de l'étape courante.
     */
    public function stepLabel(): string
    {
        return match ($this->step) {
            1 => 'Matière',
            2 => 'Détails',
            3 => 'Plans',
            default => 'Coordonnées',
        };
    }

    public function nextStep(): void
    {
        $this->validateStep();

        if ($this->step < self::totalSteps()) {
            $this->step++;
        }
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function goToStep(int $step): void
    {
        // On ne peut revenir que vers une étape déjà validée.
        if ($step < $this->step) {
            $this->step = $step;
        }
    }

    public function removePlan(int $index): void
    {
        unset($this->plans[$index]);

        $this->plans = array_values($this->plans);
    }

    /**
     * Règles de validation de l'étape courante.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function rulesForStep(): array
    {
        return match ($this->step) {
            1 => [
                'material' => [
                    'required',
                    'in:'.implode(',', array_column(self::materials(), 'value')),
                ],
                'thickness' => [
                    'required',
                    'integer',
                    'in:'.implode(',', $this->availableThicknesses()),
                ],
                'quantity' => [
                    'required',
                    'in:'.implode(',', array_column(config('site.quote.laser.quantities', []), 'value')),
                ],
            ],
            2 => [
                'complexity' => [
                    'required',
                    'in:'.implode(',', array_column(config('site.quote.laser.complexities', []), 'value')),
                ],
                'length' => ['nullable', 'integer', 'min:1', 'max:10000'],
                'width' => ['nullable', 'integer', 'min:1', 'max:10000'],
                'delay' => [
                    'required',
                    'in:'.implode(',', array_column(config('site.quote.laser.delays', []), 'value')),
                ],
                'delivery' => [
                    'required',
                    'in:'.implode(',', array_column(config('site.quote.laser.delivery', []), 'value')),
                ],
                'city' => [$this->delivery === 'livraison' ? 'required' : 'nullable', 'string', 'max:255'],
            ],
            3 => [
                'plans' => ['array', 'max:'.config('site.quote.files.max_count')],
                'plans.*' => [
                    'file',
                    'max:'.config('site.quote.files.max_size_kb'),
                    static function (string $attribute, mixed $value, callable $fail): void {
                        $extension = strtolower($value->getClientOriginalExtension());

                        // DWG n'a pas de type MIME fiable : on s'appuie sur
                        // l'extension annoncée, complétée par le contrôle de
                        // taille déjà appliqué par Livewire.
                        if (! in_array($extension, config('site.quote.files.extensions', []), true)) {
                            $fail('Formats acceptés : PDF, DXF, DWG.');
                        }
                    },
                ],
                'plansLater' => [
                    'boolean',
                    // `false` est une valeur présente pour la règle `required` :
                    // on explicite donc l'obligation d'avoir au moins un plan
                    // ou de confirmer une transmission différée.
                    function (string $attribute, mixed $value, callable $fail): void {
                        if ($this->plans === [] && $value !== true) {
                            $fail('Joignez au moins un plan, ou confirmez que vous les transmettrez autrement.');
                        }
                    },
                ],
            ],
            default => [
                'name' => ['required', 'string', 'max:255'],
                'company' => ['nullable', 'string', 'max:255'],
                'email' => ['required', 'email:rfc', 'max:255'],
                'phone' => ['nullable', 'string', 'max:32'],
                'message' => ['nullable', 'string', 'max:4000'],
            ],
        };
    }

    protected function validateStep(): void
    {
        $this->validate($this->rulesForStep());
    }

    /**
     * Charge utile envoyée au bureau d'études.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $material = collect(self::materials())->firstWhere('value', $this->material);

        return array_filter([
            'Matière' => $material['label'] ?? $this->material,
            'Épaisseur' => $this->thickness !== null ? $this->thickness.' mm' : null,
            'Quantité' => collect(config('site.quote.laser.quantities', []))
                ->firstWhere('value', $this->quantity)['label'] ?? null,
            'Type de pièces' => collect(config('site.quote.laser.complexities', []))
                ->firstWhere('value', $this->complexity)['label'] ?? null,
            'Dimensions' => ($this->length && $this->width)
                ? $this->length.' × '.$this->width.' mm'
                : null,
            'Délai' => collect(config('site.quote.laser.delays', []))
                ->firstWhere('value', $this->delay)['label'] ?? null,
            'Livraison' => $this->delivery === 'livraison'
                ? 'Livraison — '.$this->city
                : 'Retrait sur site',
            'Plans' => $this->plansLater && $this->plans === []
                ? 'À transmettre plus tard'
                : null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function submit(): void
    {
        if (trim($this->website) !== '') {
            $this->submitted = true;

            return;
        }

        $throttleKey = 'quote-laser:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, config('site.quote.rate_limit', 3))) {
            $this->addError(
                'message',
                'Trop de demandes envoyées depuis cette adresse. '
                    .'Merci de réessayer dans quelques minutes.'
            );

            return;
        }

        // Toutes les étapes sont revérifiées : un visiteur peut revenir
        // en arrière par le navigateur ou par l'interface.
        foreach (range(1, self::totalSteps()) as $step) {
            $this->step = $step;
            $this->validateStep();
        }

        RateLimiter::hit($throttleKey, 3600);

        $quote = QuoteRequest::create([
            'kind' => QuoteKind::Laser,
            'name' => trim($this->name),
            'company' => filled($this->company) ? trim($this->company) : null,
            'email' => trim($this->email),
            'phone' => filled($this->phone) ? trim($this->phone) : null,
            'city' => filled($this->city) ? trim($this->city) : null,
            'message' => filled($this->message) ? trim($this->message) : null,
            'payload' => $this->payload(),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'referer' => filled(request()->headers->get('referer'))
                ? substr(request()->headers->get('referer'), 0, 255)
                : null,
        ]);

        $this->storePlans($quote);

        try {
            Mail::to(config('site.company.email'))->send(new QuoteRequestReceived($quote));
        } catch (\Throwable $e) {
            report($e);
        }

        $this->resetAll();

        $this->submitted = true;
    }

    /**
     * Déplace les plans depuis le stockage temporaire de Livewire vers le
     * disque privé, hors de toute portée publique.
     */
    protected function storePlans(QuoteRequest $quote): void
    {
        $directory = (string) config('site.quote.storage_directory');

        foreach ($this->plans as $plan) {
            $quote->files()->create([
                'original_name' => Str::limit($plan->getClientOriginalName(), 255, ''),
                'path' => $plan->store($directory, QuoteRequestFile::disk()),
                'mime_type' => $plan->getMimeType(),
                'size' => (int) $plan->getSize(),
            ]);
        }
    }

    protected function resetAll(): void
    {
        $this->reset([
            'step',
            'material',
            'thickness',
            'quantity',
            'complexity',
            'length',
            'width',
            'delay',
            'delivery',
            'city',
            'plans',
            'plansLater',
            'name',
            'company',
            'email',
            'phone',
            'message',
            'website',
        ]);

        $this->delay = 'standard';
        $this->delivery = 'retrait';
    }
};
?>

<div>
    @if ($submitted)
        <div class="rounded-2xl border border-brand-200 bg-brand-50 p-8" role="status">
            <span class="inline-grid h-12 w-12 place-items-center rounded-full bg-brand-500 text-white">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5" />
                </svg>
            </span>

            <h3 class="mt-5 font-display text-xl font-extrabold text-ink-900">
                Demande de devis transmise
            </h3>

            <p class="mt-3 max-w-md text-sm leading-relaxed text-ink-700/70">
                Merci. Votre demande et vos plans nous sont bien parvenus.
                Notre bureau d'études revient vers vous avec un devis personnalisé.
            </p>

            <button
                type="button"
                @click="$wire.submitted = false"
                class="mt-6 inline-flex items-center gap-2 text-sm font-bold text-brand-600 transition hover:text-brand-700"
            >
                Nouvelle demande
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.19L10.7 6.03a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.24-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    @else
        {{-- Progression --}}
        <ol class="mb-8 flex flex-wrap gap-x-6 gap-y-3">
            @foreach (range(1, self::totalSteps()) as $index)
                @php
                    $label = match ($index) {
                        1 => 'Matière',
                        2 => 'Détails',
                        3 => 'Plans',
                        default => 'Coordonnées',
                    };
                @endphp
                <li>
                    <button
                        type="button"
                        wire:click="goToStep({{ $index }})"
                        @disabled($index > $step)
                        @if ($index > $step) aria-disabled="true" @endif
                        class="flex items-center gap-2 text-sm font-bold transition @if ($index === $step) text-brand-600 @elseif ($index < $step) text-ink-900 hover:text-brand-600 @else text-ink-700/35 @endif"
                    >
                        <span
                            class="grid h-6 w-6 place-items-center rounded-full text-[11px] @if ($index === $step) bg-brand-500 text-white @elseif ($index < $step) bg-brand-100 text-brand-600 @else bg-mist-200 text-ink-700/40 @endif"
                        >
                            @if ($index < $step)
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5" />
                                </svg>
                            @else
                                {{ $index }}
                            @endif
                        </span>
                        {{ $label }}
                    </button>
                </li>
            @endforeach
        </ol>

        <form wire:submit="submit" class="space-y-8">
            {{-- Étape 1 — matière, épaisseur, quantité --}}
            @if ($step === 1)
                <div class="space-y-6">
                    <div>
                        <span class="mb-3 block text-sm font-bold text-ink-900">
                            Matière <span class="text-brand-600">*</span>
                        </span>

                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach (self::materials() as $option)
                                <label
                                    class="cursor-pointer rounded-xl border p-4 transition @if ($material === $option['value']) border-brand-500 bg-brand-50 ring-2 ring-brand-500/20 @else border-mist-300 bg-white hover:border-brand-300 @endif"
                                >
                                    <input
                                        type="radio"
                                        wire:model.live="material"
                                        name="material"
                                        value="{{ $option['value'] }}"
                                        class="sr-only"
                                    >
                                    <span class="block font-display text-base font-extrabold text-ink-900">
                                        {{ $option['label'] }}
                                    </span>
                                    <span class="mt-1 block text-xs leading-relaxed text-ink-700/60">
                                        {{ $option['hint'] }}
                                    </span>
                                    <span class="mt-2 block text-xs font-bold text-brand-600">
                                        Jusqu'à {{ $option['max_thickness'] }} mm
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @error('material')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="laser-thickness" class="mb-2 block text-sm font-bold text-ink-900">
                            Épaisseur <span class="text-brand-600">*</span>
                        </label>

                        <select
                            id="laser-thickness"
                            wire:model="thickness"
                            @disabled($material === '')
                            @class([
                                'w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-mist-100',
                                'border-red-400' => $errors->has('thickness'),
                            ])
                        >
                            <option value="">{{ $material === '' ? 'Choisissez d\'abord une matière' : 'Sélectionnez une épaisseur' }}</option>

                            @foreach ($this->availableThicknesses() as $option)
                                <option value="{{ $option }}">{{ $option }} mm</option>
                            @endforeach
                        </select>

                        @error('thickness')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <span class="mb-3 block text-sm font-bold text-ink-900">
                            Quantité <span class="text-brand-600">*</span>
                        </span>

                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach (config('site.quote.laser.quantities', []) as $option)
                                <label
                                    class="flex cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 transition @if ($quantity === $option['value']) border-brand-500 bg-brand-50 ring-2 ring-brand-500/20 @else border-mist-300 bg-white hover:border-brand-300 @endif"
                                >
                                    <input
                                        type="radio"
                                        wire:model="quantity"
                                        name="quantity"
                                        value="{{ $option['value'] }}"
                                        class="h-4 w-4 border-mist-300 text-brand-600 focus:ring-brand-500"
                                    >
                                    <span class="text-sm font-semibold text-ink-900">{{ $option['label'] }}</span>
                                </label>
                            @endforeach
                        </div>

                        @error('quantity')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif

            {{-- Étape 2 — détails techniques --}}
            @if ($step === 2)
                <div class="space-y-6">
                    <div>
                        <span class="mb-3 block text-sm font-bold text-ink-900">
                            Type de pièces <span class="text-brand-600">*</span>
                        </span>

                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach (config('site.quote.laser.complexities', []) as $option)
                                <label
                                    class="cursor-pointer rounded-xl border p-4 transition @if ($complexity === $option['value']) border-brand-500 bg-brand-50 ring-2 ring-brand-500/20 @else border-mist-300 bg-white hover:border-brand-300 @endif"
                                >
                                    <input
                                        type="radio"
                                        wire:model="complexity"
                                        name="complexity"
                                        value="{{ $option['value'] }}"
                                        class="sr-only"
                                    >
                                    <span class="block font-display text-base font-extrabold text-ink-900">
                                        {{ $option['label'] }}
                                    </span>
                                    <span class="mt-1 block text-xs leading-relaxed text-ink-700/60">
                                        {{ $option['hint'] }}
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @error('complexity')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <span class="mb-2 block text-sm font-bold text-ink-900">
                            Format des pièces
                            <span class="ml-1 text-xs font-semibold text-ink-700/50">
                                (facultatif — plâtre de travail 4 000 × 2 000 mm)
                            </span>
                        </span>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="laser-length" class="mb-2 block text-xs font-bold text-ink-700/70">Longueur (mm)</label>
                                <input
                                    id="laser-length"
                                    type="number"
                                    wire:model="length"
                                    min="1"
                                    max="10000"
                                    placeholder="500"
                                    @class([
                                        'w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20',
                                        'border-red-400' => $errors->has('length'),
                                    ])
                                >
                            </div>

                            <div>
                                <label for="laser-width" class="mb-2 block text-xs font-bold text-ink-700/70">Largeur (mm)</label>
                                <input
                                    id="laser-width"
                                    type="number"
                                    wire:model="width"
                                    min="1"
                                    max="10000"
                                    placeholder="300"
                                    @class([
                                        'w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20',
                                        'border-red-400' => $errors->has('width'),
                                    ])
                                >
                            </div>
                        </div>

                        @error('length')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                        @error('width')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label for="laser-delay" class="mb-2 block text-sm font-bold text-ink-900">
                                Délai souhaité <span class="text-brand-600">*</span>
                            </label>
                            <select
                                id="laser-delay"
                                wire:model="delay"
                                @class([
                                    'w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20',
                                    'border-red-400' => $errors->has('delay'),
                                ])
                            >
                                @foreach (config('site.quote.laser.delays', []) as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                            @error('delay')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="laser-delivery" class="mb-2 block text-sm font-bold text-ink-900">
                                Enlèvement ou livraison <span class="text-brand-600">*</span>
                            </label>
                            <select
                                id="laser-delivery"
                                wire:model.live="delivery"
                                @class([
                                    'w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20',
                                    'border-red-400' => $errors->has('delivery'),
                                ])
                            >
                                @foreach (config('site.quote.laser.delivery', []) as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                            @error('delivery')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @if ($delivery === 'livraison')
                        <div>
                            <label for="laser-city" class="mb-2 block text-sm font-bold text-ink-900">
                                Commune de livraison <span class="text-brand-600">*</span>
                            </label>
                            <input
                                id="laser-city"
                                type="text"
                                wire:model="city"
                                autocomplete="address-level2"
                                placeholder="Montaigu-Vendée"
                                @class([
                                    'w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20',
                                    'border-red-400' => $errors->has('city'),
                                ])
                            >
                            @error('city')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif
                </div>
            @endif

            {{-- Étape 3 — plans --}}
            @if ($step === 3)
                <div class="space-y-6">
                    <div>
                        <span class="mb-2 block text-sm font-bold text-ink-900">
                            Vos plans <span class="text-ink-700/40">(facultatif)</span>
                        </span>
                        <p class="mb-4 text-sm leading-relaxed text-ink-700/60">
                            Joignez vos fichiers de découpe ou vos plans pour accélérer l'étude.
                            <strong class="font-bold text-ink-900">PDF, DXF ou DWG</strong>,
                            {{ config('site.quote.files.max_count') }} fichiers maximum,
                            {{ round(config('site.quote.files.max_size_kb') / 1024) }} Mo par fichier.
                        </p>

                        <label
                            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-mist-300 bg-mist-50 px-6 py-10 text-center transition hover:border-brand-400 hover:bg-brand-50"
                        >
                            <input
                                type="file"
                                wire:model="plans"
                                multiple
                                accept=".pdf,.dxf,.dwg"
                                class="sr-only"
                            >

                            <span class="inline-grid h-11 w-11 place-items-center rounded-full bg-brand-100 text-brand-600">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                </svg>
                            </span>

                            <span class="mt-3 text-sm font-bold text-ink-900">
                                Déposez vos fichiers ou cliquez pour parcourir
                            </span>
                        </label>

                        @error('plans')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                        @error('plans.*')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    @if (count($plans) > 0)
                        <ul class="divide-y divide-mist-200 rounded-xl border border-mist-200 bg-white">
                            @foreach ($plans as $index => $plan)
                                <li class="flex items-center gap-3 px-4 py-3" wire:key="plan-{{ $index }}-{{ $plan->hashName() }}">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                        </svg>
                                    </span>

                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-ink-900">
                                            {{ $plan->getClientOriginalName() }}
                                        </span>
                                        <span class="block text-xs text-ink-700/50">
                                            {{ number_format($plan->getSize() / 1024, 0, ',', ' ') }} Ko
                                        </span>
                                    </span>

                                    <button
                                        type="button"
                                        wire:click="removePlan({{ $index }})"
                                        class="shrink-0 text-ink-700/40 transition hover:text-red-600"
                                        aria-label="Retirer ce fichier"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <label class="flex cursor-pointer items-start gap-3">
                        <input
                            type="checkbox"
                            wire:model="plansLater"
                            class="mt-0.5 h-4 w-4 rounded border-mist-300 text-brand-600 focus:ring-brand-500"
                        >
                        <span class="text-sm leading-relaxed text-ink-700/70">
                            Je transmettrai mes plans par e-mail ou par une autre voie.
                        </span>
                    </label>

                    @error('plansLater')
                        <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            {{-- Étape 4 — coordonnées et récapitulatif --}}
            @if ($step === 4)
                <div class="space-y-6">
                    <div class="rounded-xl border border-mist-200 bg-mist-50 p-5">
                        <p class="mb-3 text-xs font-bold uppercase tracking-wider text-ink-700/50">
                            Récapitulatif
                        </p>

                        <dl class="space-y-2">
                            @foreach ($this->payload() as $key => $value)
                                <div class="flex flex-wrap items-baseline gap-x-3 text-sm">
                                    <dt class="shrink-0 font-semibold text-ink-700/60">{{ $key }}</dt>
                                    <dd class="font-bold text-ink-900">{{ $value }}</dd>
                                </div>
                            @endforeach

                            @if (count($plans) > 0)
                                <div class="flex flex-wrap items-baseline gap-x-3 text-sm">
                                    <dt class="shrink-0 font-semibold text-ink-700/60">Fichiers joints</dt>
                                    <dd class="font-bold text-ink-900">
                                        {{ count($plans) }} fichier(s)
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="laser-name" class="mb-2 block text-sm font-bold text-ink-900">
                                Nom <span class="text-brand-600">*</span>
                            </label>
                            <input
                                id="laser-name"
                                type="text"
                                wire:model="name"
                                autocomplete="name"
                                placeholder="Jean Dupont"
                                @class([
                                    'w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20',
                                    'border-red-400' => $errors->has('name'),
                                ])
                            >
                            @error('name')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="laser-company" class="mb-2 block text-sm font-bold text-ink-900">Société</label>
                            <input
                                id="laser-company"
                                type="text"
                                wire:model="company"
                                autocomplete="organization"
                                placeholder="Charpentes Dupont"
                                @class([
                                    'w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20',
                                    'border-red-400' => $errors->has('company'),
                                ])
                            >
                            @error('company')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="laser-email" class="mb-2 block text-sm font-bold text-ink-900">
                                E-mail <span class="text-brand-600">*</span>
                            </label>
                            <input
                                id="laser-email"
                                type="email"
                                wire:model="email"
                                autocomplete="email"
                                placeholder="jean@dupont.fr"
                                @class([
                                    'w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20',
                                    'border-red-400' => $errors->has('email'),
                                ])
                            >
                            @error('email')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="laser-phone" class="mb-2 block text-sm font-bold text-ink-900">Téléphone</label>
                            <input
                                id="laser-phone"
                                type="tel"
                                wire:model="phone"
                                autocomplete="tel"
                                placeholder="06 12 34 56 78"
                                @class([
                                    'w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20',
                                    'border-red-400' => $errors->has('phone'),
                                ])
                            >
                            @error('phone')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="laser-message" class="mb-2 block text-sm font-bold text-ink-900">
                            Précisions utiles
                        </label>
                        <textarea
                            id="laser-message"
                            wire:model="message"
                            rows="4"
                            placeholder="Contraintes de tolérance, découpe particulière, délai impératif…"
                            @class([
                                'w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20',
                                'border-red-400' => $errors->has('message'),
                            ])
                        ></textarea>
                        @error('message')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="relative hidden" aria-hidden="true">
                        <label for="laser-website">Site web</label>
                        <input id="laser-website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
                    </div>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-3 border-t border-mist-200 pt-6">
                @if ($step > 1)
                    <button
                        type="button"
                        wire:click="previousStep"
                        class="inline-flex items-center gap-2 rounded-lg border border-mist-300 bg-white px-5 py-3 text-sm font-bold text-ink-900 transition hover:border-brand-400 hover:text-brand-600"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M17 10a.75.75 0 0 1-.75.75H5.612l4.158 3.96a.75.75 0 1 1-1.04 1.08l-5.5-5.25a.75.75 0 0 1 0-1.08l5.5-5.25a.75.75 0 1 1 1.04 1.08L5.612 9.25H16.25A.75.75 0 0 1 17 10Z" clip-rule="evenodd" />
                        </svg>
                        Retour
                    </button>
                @endif

                @if ($step < self::totalSteps())
                    <button
                        type="button"
                        wire:click="nextStep"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-600"
                    >
                        Continuer
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.19L10.7 6.03a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.24-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                        </svg>
                    </button>
                @else
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-6 py-3 text-sm font-bold text-white transition hover:bg-brand-600 disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="submit">Envoyer ma demande</span>
                        <span wire:loading wire:target="submit">Envoi en cours…</span>
                    </button>
                @endif
            </div>
        </form>
    @endif
</div>

