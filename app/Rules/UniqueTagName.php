<?php

namespace App\Rules;

use App\Models\CrmTag;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Un nom d'étiquette doit être unique indépendamment de la casse, des accents
 * et de la ponctuation.
 *
 * La colonne `slug` porte déjà une contrainte unique et est dérivée du nom via
 * Str::slug(), qui normalise précisément ces différences : vérifier le slug
 * suffit à éviter l'erreur brute de base de données.
 */
final class UniqueTagName implements ValidationRule
{
    public function __construct(
        private readonly ?CrmTag $ignorable = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Le nom de l’étiquette est invalide.');

            return;
        }

        $slug = Str::slug(trim($value));

        if ($slug === '') {
            $fail('Le nom de l’étiquette doit contenir au moins une lettre ou un chiffre.');

            return;
        }

        $taken = CrmTag::query()
            ->where('slug', $slug)
            ->when(
                $this->ignorable instanceof CrmTag,
                fn ($query) => $query->whereKeyNot($this->ignorable->getKey()),
            )
            ->exists();

        if ($taken) {
            $fail('Une étiquette portant ce nom existe déjà.');
        }
    }

    /**
     * Fabrique la règle à partir de la fiche en cours d'édition, ou d'aucune
     * fiche pour une création.
     */
    public static function forRecord(mixed $record): self
    {
        if ($record instanceof CrmTag) {
            return new self($record);
        }

        if ($record === null) {
            return new self;
        }

        throw new InvalidArgumentException(
            'UniqueTagName attend une étiquette ou null, '.get_debug_type($record).' reçu.',
        );
    }
}
