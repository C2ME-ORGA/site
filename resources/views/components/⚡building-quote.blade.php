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

    /** Étape 1 — nature du projet. */
    public string $type = '';

    public string $usage = '';

    /** Étape 2 — implantation. */
    public ?int $length = null;

    public ?int $width = null;

    public ?int $height = null;

    public string $city = '';

    /** Étape 3 — enveloppe et prestations. */
    public string $envelope = '';

    /** @var list<string> */
    public array $prestations = [];

    /** Étape 4 — calendrier et budget. */
    public string $delay = 'inconnu';

    public string $budget = 'inconnu';

    public string $level = 'moa';

    /** Étape 5 — plans. */
    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $plans = [];

    public bool $plansLater = false;

    /** Étape 6 — coordonnées. */
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
        return 6;
    }

    /**
     * Libellé lisible de l'étape courante.
     */
    public function stepLabel(): string
    {
        return match ($this->step) {
            1 => 'Projet',
            2 => 'Implantation',
            3 => 'Enveloppe',
            4 => 'Cadres',
            5 => 'Plans',
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
        // On ne peut revenir que vers une étape déjà franchie.
        if ($step < $this->step) {
            $this->step = $step;
        }
    }

    public function togglePrestation(string $value): void
    {
        in_array($value, $this->prestations, true)
            ? $this->prestations = array_values(array_diff($this->prestations, [$value]))
            : $this->prestations[] = $value;
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
                'type' => [
                    'required',
                    'in:'.implode(',', array_column(config('site.quote.building.types', []), 'value')),
                ],
                'usage' => [
                    'required',
                    'in:'.implode(',', array_column(config('site.quote.building.usages', []), 'value')),
                ],
            ],
            2 => [
                'length' => ['nullable', 'integer', 'min:1', 'max:200'],
                'width' => ['nullable', 'integer', 'min:1', 'max:200'],
                'height' => ['nullable', 'integer', 'min:1', 'max:60'],
                'city' => ['required', 'string', 'max:255'],
            ],
            3 => [
                'envelope' => [
                    'required',
                    'in:'.implode(',', array_column(config('site.quote.building.enveloppes', []), 'value')),
                ],
                'prestations' => ['array', 'max:'.count(config('site.quote.building.prestations', []))],
                'prestations.*' => [
                    'in:'.implode(',', array_column(config('site.quote.building.prestations', []), 'value')),
                ],
            ],
            4 => [
                'delay' => [
                    'required',
                    'in:'.implode(',', array_column(config('site.quote.building.delays', []), 'value')),
                ],
                'budget' => [
                    'required',
                    'in:'.implode(',', array_column(config('site.quote.building.budgets', []), 'value')),
                ],
                'level' => [
                    'required',
                    'in:'.implode(',', array_column(config('site.quote.building.levels', []), 'value')),
                ],
            ],
            5 => [
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
     * Libellé lisible d'une valeur de configuration.
     */
    protected function labelFor(string $group, string $value): ?string
    {
        return collect(config('site.quote.building.'.$group, []))
            ->firstWhere('value', $value)['label'] ?? null;
    }

    /**
     * Charge utile envoyée au bureau d'études.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $dimensions = array_filter([
            $this->length ? 'L '.$this->length.' m' : null,
            $this->width ? 'l '.$this->width.' m' : null,
            $this->height ? 'H '.$this->height.' m' : null,
        ]);

        return array_filter([
            'Type de bâtiment' => $this->labelFor('types', $this->type),
            'Usage' => $this->labelFor('usages', $this->usage),
            'Commune' => filled($this->city) ? $this->city : null,
            'Dimensions' => $dimensions !== [] ? implode(' · ', $dimensions) : null,
            'Enveloppe' => $this->labelFor('enveloppes', $this->envelope),
            'Prestations' => $this->prestations !== []
                ? implode(' · ', array_map(
                    fn (string $value): ?string => $this->labelFor('prestations', $value),
                    $this->prestations,
                ))
                : null,
            'Calendrier' => $this->labelFor('delays', $this->delay),
            'Budget' => $this->labelFor('budgets', $this->budget),
            'Vous êtes' => $this->labelFor('levels', $this->level),
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

        $throttleKey = 'quote-building:'.request()->ip();

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
            'kind' => QuoteKind::Building,
            'name' => trim($this->name),
            'company' => filled($this->company) ? trim($this->company) : null,
            'email' => trim($this->email),
            'phone' => filled($this->phone) ? trim($this->phone) : null,
            'city' => trim($this->city),
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
            'type',
            'usage',
            'length',
            'width',
            'height',
            'city',
            'envelope',
            'prestations',
            'delay',
            'budget',
            'level',
            'plans',
            'plansLater',
            'name',
            'company',
            'email',
            'phone',
            'message',
            'website',
        ]);

        $this->delay = 'inconnu';
        $this->budget = 'inconnu';
        $this->level = 'moa';
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
                Votre projet nous est parvenu
            </h3>

            <p class="mt-3 max-w-md text-sm leading-relaxed text-ink-700/70">
                Merci. Notre bureau d'études étudie votre brief et revient vers vous
                avec une proposition adaptée à votre chantier.
            </p>

            <button
                type="button"
                @click="$wire.submitted = false"
                class="mt-6 inline-flex items-center gap-2 text-sm font-bold text-brand-600 transition hover:text-brand-700"
            >
                Décrire un autre projet
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.19L10.7 6.03a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.24-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    @else
        {{-- Progression --}}
        <ol class="mb-8 flex flex-wrap gap-x-5 gap-y-3">
            @foreach (range(1, self::totalSteps()) as $index)
                @php
                    $label = match ($index) {
                        1 => 'Projet',
                        2 => 'Implantation',
                        3 => 'Enveloppe',
                        4 => 'Cadres',
                        5 => 'Plans',
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
            {{-- Étape 1 — nature du projet --}}
            @if ($step === 1)
                <div class="space-y-6">
                    <div>
                        <span class="mb-3 block text-sm font-bold text-ink-900">
                            Nature du bâtiment <span class="text-brand-600">*</span>
                        </span>

                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach (config('site.quote.building.types', []) as $option)
                                <label
                                    class="cursor-pointer rounded-xl border p-4 transition @if ($type === $option['value']) border-brand-500 bg-brand-50 ring-2 ring-brand-500/20 @else border-mist-300 bg-white hover:border-brand-300 @endif"
                                >
                                    <input
                                        type="radio"
                                        wire:model="type"
                                        name="type"
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

                        @error('type')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="building-usage" class="mb-2 block text-sm font-bold text-ink-900">
                            Usage prévu <span class="text-brand-600">*</span>
                        </label>
                        <select
                            id="building-usage"
                            wire:model="usage"
                            class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                            :class="$errors->has('usage') ? 'border-red-400' : ''"
                        >
                            <option value="">Sélectionnez un usage</option>
                            @foreach (config('site.quote.building.usages', []) as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        @error('usage')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif

            {{-- Étape 2 — implantation --}}
            @if ($step === 2)
                <div class="space-y-6">
                    <div>
                        <span class="mb-2 block text-sm font-bold text-ink-900">
                            Emprise du bâtiment
                            <span class="ml-1 text-xs font-semibold text-ink-700/50">(facultatif — une estimation suffit)</span>
                        </span>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label for="building-length" class="mb-2 block text-xs font-bold text-ink-700/70">Longueur (m)</label>
                                <input
                                    id="building-length"
                                    type="number"
                                    wire:model="length"
                                    min="1"
                                    max="200"
                                    placeholder="30"
                                    class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    :class="$errors->has('length') ? 'border-red-400' : ''"
                                >
                            </div>

                            <div>
                                <label for="building-width" class="mb-2 block text-xs font-bold text-ink-700/70">Largeur (m)</label>
                                <input
                                    id="building-width"
                                    type="number"
                                    wire:model="width"
                                    min="1"
                                    max="200"
                                    placeholder="20"
                                    class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    :class="$errors->has('width') ? 'border-red-400' : ''"
                                >
                            </div>

                            <div>
                                <label for="building-height" class="mb-2 block text-xs font-bold text-ink-700/70">Hauteur sous faîtage (m)</label>
                                <input
                                    id="building-height"
                                    type="number"
                                    wire:model="height"
                                    min="1"
                                    max="60"
                                    placeholder="8"
                                    class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    :class="$errors->has('height') ? 'border-red-400' : ''"
                                >
                            </div>
                        </div>

                        @error('length')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                        @error('width')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                        @error('height')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="building-city" class="mb-2 block text-sm font-bold text-ink-900">
                            Commune du chantier <span class="text-brand-600">*</span>
                        </label>
                        <input
                            id="building-city"
                            type="text"
                            wire:model="city"
                            autocomplete="address-level2"
                            placeholder="Montaigu-Vendée"
                            class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                            :class="$errors->has('city') ? 'border-red-400' : ''"
                        >
                        <p class="mt-2 text-xs text-ink-700/50">
                            Elle détermine la faisabilité du transport et du montage.
                        </p>
                        @error('city')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif

            {{-- Étape 3 — enveloppe et prestations --}}
            @if ($step === 3)
                <div class="space-y-6">
                    <div>
                        <span class="mb-3 block text-sm font-bold text-ink-900">
                            Niveau de livraison <span class="text-brand-600">*</span>
                        </span>

                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach (config('site.quote.building.enveloppes', []) as $option)
                                <label
                                    class="cursor-pointer rounded-xl border p-4 transition @if ($envelope === $option['value']) border-brand-500 bg-brand-50 ring-2 ring-brand-500/20 @else border-mist-300 bg-white hover:border-brand-300 @endif"
                                >
                                    <input
                                        type="radio"
                                        wire:model="envelope"
                                        name="envelope"
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

                        @error('envelope')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <span class="mb-3 block text-sm font-bold text-ink-900">
                            Prestations souhaitées
                            <span class="ml-1 text-xs font-semibold text-ink-700/50">(facultatif)</span>
                        </span>

                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach (config('site.quote.building.prestations', []) as $option)
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-lg border px-4 py-3 transition @if (in_array($option['value'], $prestations, true)) border-brand-500 bg-brand-50 ring-2 ring-brand-500/20 @else border-mist-300 bg-white hover:border-brand-300 @endif"
                                >
                                    <input
                                        type="checkbox"
                                        wire:click="togglePrestation('{{ $option['value'] }}')"
                                        @checked(in_array($option['value'], $prestations, true))
                                        class="mt-0.5 h-4 w-4 rounded border-mist-300 text-brand-600 focus:ring-brand-500"
                                    >
                                    <span>
                                        <span class="block text-sm font-bold text-ink-900">{{ $option['label'] }}</span>
                                        <span class="mt-0.5 block text-xs leading-relaxed text-ink-700/60">
                                            {{ $option['hint'] }}
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @error('prestations')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif

            {{-- Étape 4 — calendrier et budget --}}
            @if ($step === 4)
                <div class="space-y-6">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label for="building-delay" class="mb-2 block text-sm font-bold text-ink-900">
                                Calendrier <span class="text-brand-600">*</span>
                            </label>
                            <select
                                id="building-delay"
                                wire:model="delay"
                                class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                :class="$errors->has('delay') ? 'border-red-400' : ''"
                            >
                                @foreach (config('site.quote.building.delays', []) as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                            @error('delay')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="building-budget" class="mb-2 block text-sm font-bold text-ink-900">
                                Enveloppe budgétaire <span class="text-brand-600">*</span>
                            </label>
                            <select
                                id="building-budget"
                                wire:model="budget"
                                class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                :class="$errors->has('budget') ? 'border-red-400' : ''"
                            >
                                @foreach (config('site.quote.building.budgets', []) as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-xs text-ink-700/50">
                                Une fourchette nous aide à cadrer la proposition.
                            </p>
                            @error('budget')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="building-level" class="mb-2 block text-sm font-bold text-ink-900">
                            Vous portez le projet en tant que <span class="text-brand-600">*</span>
                        </label>
                        <select
                            id="building-level"
                            wire:model="level"
                            class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                            :class="$errors->has('level') ? 'border-red-400' : ''"
                        >
                            @foreach (config('site.quote.building.levels', []) as $option)
                                <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                        @error('level')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif

            {{-- Étape 5 — plans --}}
            @if ($step === 5)
                <div class="space-y-6">
                    <div>
                        <span class="mb-2 block text-sm font-bold text-ink-900">
                            Plans et documents <span class="text-ink-700/40">(facultatif)</span>
                        </span>
                        <p class="mb-4 text-sm leading-relaxed text-ink-700/60">
                            Plan de masse, implantation, relevé de terrain ou esquisse.
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
                                Déposez vos documents ou cliquez pour parcourir
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
                                <li class="flex items-center gap-3 px-4 py-3" wire:key="building-plan-{{ $index }}-{{ $plan->hashName() }}">
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
                            Je transmettrai mes documents par e-mail ou par une autre voie.
                        </span>
                    </label>

                    @error('plansLater')
                        <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            {{-- Étape 6 — coordonnées et récapitulatif --}}
            @if ($step === 6)
                <div class="space-y-6">
                    <div class="rounded-xl border border-mist-200 bg-mist-50 p-5">
                        <p class="mb-3 text-xs font-bold uppercase tracking-wider text-ink-700/50">
                            Récapitulatif de votre projet
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
                            <label for="building-name" class="mb-2 block text-sm font-bold text-ink-900">
                                Nom <span class="text-brand-600">*</span>
                            </label>
                            <input
                                id="building-name"
                                type="text"
                                wire:model="name"
                                autocomplete="name"
                                placeholder="Jean Dupont"
                                class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                :class="$errors->has('name') ? 'border-red-400' : ''"
                            >
                            @error('name')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="building-company" class="mb-2 block text-sm font-bold text-ink-900">Société</label>
                            <input
                                id="building-company"
                                type="text"
                                wire:model="company"
                                autocomplete="organization"
                                placeholder="Charpentes Dupont"
                                class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                :class="$errors->has('company') ? 'border-red-400' : ''"
                            >
                            @error('company')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="building-email" class="mb-2 block text-sm font-bold text-ink-900">
                                E-mail <span class="text-brand-600">*</span>
                            </label>
                            <input
                                id="building-email"
                                type="email"
                                wire:model="email"
                                autocomplete="email"
                                placeholder="jean@dupont.fr"
                                class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                :class="$errors->has('email') ? 'border-red-400' : ''"
                            >
                            @error('email')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="building-phone" class="mb-2 block text-sm font-bold text-ink-900">Téléphone</label>
                            <input
                                id="building-phone"
                                type="tel"
                                wire:model="phone"
                                autocomplete="tel"
                                placeholder="06 12 34 56 78"
                                class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                :class="$errors->has('phone') ? 'border-red-400' : ''"
                            >
                            @error('phone')
                                <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="building-message" class="mb-2 block text-sm font-bold text-ink-900">
                            Précisions utiles
                        </label>
                        <textarea
                            id="building-message"
                            wire:model="message"
                            rows="4"
                            placeholder="Contraintes de terrain, raccordements, règles d'urbanisme, contraintes d'accès…"
                            class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                            :class="$errors->has('message') ? 'border-red-400' : ''"
                        ></textarea>
                        @error('message')
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="relative hidden" aria-hidden="true">
                        <label for="building-website">Site web</label>
                        <input id="building-website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
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
                        <span wire:loading.remove wire:target="submit">Envoyer mon projet</span>
                        <span wire:loading wire:target="submit">Envoi en cours…</span>
                    </button>
                @endif
            </div>
        </form>
    @endif
</div>

