<?php

use App\Mail\ContactRequestReceived;
use App\Models\ContactRequest;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $company = '';

    public string $email = '';

    public string $phone = '';

    public string $requestType = '';

    public string $message = '';

    /** Pot de miel : doit rester vide. */
    public string $website = '';

    public bool $submitted = false;

    /**
     * Le visiteur arrive souvent depuis une fiche de service : on présélectionne
     * le domaine correspondant pour lui éviter une saisie inutile.
     */
    public function mount(): void
    {
        $this->requestType = $this->guessRequestTypeFromReferrer();
    }

    /**
     * Retourne le pôle correspondant à la page d'origine, le cas échéant.
     */
    protected function guessRequestTypeFromReferrer(): string
    {
        $referer = request()->headers->get('referer');

        if (! filled($referer)) {
            return '';
        }

        $referrerPath = trim((string) parse_url($referer, PHP_URL_PATH), '/');

        if ($referrerPath === '') {
            return '';
        }

        foreach (config('site.services', []) as $service) {
            $servicePath = trim((string) parse_url(
                route($service['route'], absolute: false),
                PHP_URL_PATH
            ), '/');

            if ($servicePath !== '' && $servicePath === $referrerPath) {
                return $service['name'];
            }
        }

        return '';
    }

    /**
     * Types proposés au visiteur, alignés sur les pôles de services.
     *
     * @return array<int, string>
     */
    public static function requestTypes(): array
    {
        $types = array_column(config('site.services'), 'name');

        $types[] = 'Autre demande';

        return $types;
    }

    public function submit(): void
    {
        // Un humain ne remplit jamais ce champ : on renvoie une fausse
        // réussite pour ne pas signaler au bot qu'il a été détecté.
        if (trim($this->website) !== '') {
            $this->submitted = true;

            return;
        }

        $throttleKey = 'contact-form:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $this->addError(
                'message',
                'Trop de demandes envoyées depuis cette adresse. '
                    .'Merci de réessayer dans quelques minutes.'
            );

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'requestType' => ['required', 'string', 'in:'.implode(',', self::requestTypes())],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        RateLimiter::hit($throttleKey, 3600);

        $contact = ContactRequest::create([
            'name' => trim($this->name),
            'company' => filled($this->company) ? trim($this->company) : null,
            'email' => trim($this->email),
            'phone' => filled($this->phone) ? trim($this->phone) : null,
            'request_type' => $this->requestType,
            'message' => trim($this->message),
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'referer' => filled(request()->headers->get('referer'))
                ? substr(request()->headers->get('referer'), 0, 255)
                : null,
        ]);

        // La demande est déjà en base : un échec d'envoi ne doit pas
        // faire échouer le formulaire du visiteur.
        try {
            Mail::to(config('site.company.email'))->send(new ContactRequestReceived($contact));
        } catch (\Throwable $e) {
            report($e);
        }

        $this->reset([
            'name',
            'company',
            'email',
            'phone',
            'requestType',
            'message',
            'website',
        ]);

        $this->submitted = true;
    }
};
?>

<div>
    @if ($submitted)
        <div
            class="rounded-2xl border border-brand-200 bg-brand-50 p-8"
            role="status"
        >
            <span class="inline-grid h-12 w-12 place-items-center rounded-full bg-brand-500 text-white">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5" />
                </svg>
            </span>

            <h3 class="mt-5 font-display text-xl font-extrabold text-ink-900">
                Demande envoyée
            </h3>

            <p class="mt-3 max-w-md text-sm leading-relaxed text-ink-700/70">
                Merci, votre message a bien été transmis à notre équipe.
                Nous revenons vers vous dans les plus brefs délais.
            </p>

            <button
                type="button"
                @click="$wire.submitted = false"
                class="mt-6 inline-flex items-center gap-2 text-sm font-bold text-brand-600 transition hover:text-brand-700"
            >
                Envoyer une autre demande
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.19L10.7 6.03a.75.75 0 1 1 1.06-1.06l4.5 4.5a.75.75 0 0 1 0 1.06l-4.5 4.5a.75.75 0 1 1-1.06-1.06l3.24-3.22H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    @else
        <form wire:submit="submit" class="space-y-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="contact-name" class="mb-2 block text-sm font-bold text-ink-900">
                        Nom <span class="text-brand-600">*</span>
                    </label>
                    <input
                        id="contact-name"
                        type="text"
                        wire:model="name"
                        autocomplete="name"
                        class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                        :class="$errors->has('name') ? 'border-red-400' : ''"
                        placeholder="Jean Dupont"
                    >
                    @error('name')
                        <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
    @enderror
                </div>

                <div>
                    <label for="contact-company" class="mb-2 block text-sm font-bold text-ink-900">
                        Société
                    </label>
                    <input
                        id="contact-company"
                        type="text"
                        wire:model="company"
                        autocomplete="organization"
                        class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                        placeholder="Nom de votre entreprise"
                    >
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="contact-email" class="mb-2 block text-sm font-bold text-ink-900">
                        E-mail <span class="text-brand-600">*</span>
                    </label>
                    <input
                        id="contact-email"
                        type="email"
                        wire:model="email"
                        autocomplete="email"
                        class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                        :class="$errors->has('email') ? 'border-red-400' : ''"
                        placeholder="vous@exemple.fr"
                    >
                    @error('email')
                        <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="contact-phone" class="mb-2 block text-sm font-bold text-ink-900">
                        Téléphone
                    </label>
                    <input
                        id="contact-phone"
                        type="tel"
                        wire:model="phone"
                        autocomplete="tel"
                        class="w-full rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                        placeholder="06 00 00 00 00"
                    >
                </div>
            </div>

            <div>
                <label for="contact-type" class="mb-2 block text-sm font-bold text-ink-900">
                    Type de demande <span class="text-brand-600">*</span>
                </label>
                <select
                    id="contact-type"
                    wire:model="requestType"
                    class="w-full appearance-none rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                    :class="$errors->has('requestType') ? 'border-red-400' : ''"
                >
                    <option value="">Sélectionnez un domaine</option>
                    @foreach (self::requestTypes() as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
                @error('requestType')
                    <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="contact-message" class="mb-2 block text-sm font-bold text-ink-900">
                    Message <span class="text-brand-600">*</span>
                </label>
                <textarea
                    id="contact-message"
                    wire:model="message"
                    rows="6"
                    class="w-full resize-y rounded-lg border border-mist-300 bg-white px-4 py-3 text-sm text-ink-900 transition placeholder:text-ink-700/40 focus:border-brand-400 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                    :class="$errors->has('message') ? 'border-red-400' : ''"
                    placeholder="Décrivez votre projet, les quantités, les délais souhaités…"
                ></textarea>
                @error('message')
                    <p class="mt-2 text-xs font-semibold text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Pot de miel : visible pour les robots, hors écran pour les humains --}}
            <div class="absolute left-[-9999px] top-auto h-px w-px overflow-hidden" aria-hidden="true">
                <label for="contact-website">Site web</label>
                <input
                    id="contact-website"
                    type="text"
                    wire:model="website"
                    tabindex="-1"
                    autocomplete="off"
                >
            </div>

            <div class="flex items-center justify-between gap-6 pt-2">
                <p class="text-xs leading-relaxed text-ink-700/60">
                    Les champs marqués d'un <span class="text-brand-600">*</span> sont obligatoires.
                </p>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-6 py-3.5 text-base font-bold text-white transition hover:bg-brand-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="submit">Envoyer la demande</span>
                    <span wire:loading wire:target="submit">Envoi en cours…</span>
                </button>
            </div>
        </form>
    @endif
</div>
