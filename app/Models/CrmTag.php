<?php

namespace App\Models;

use App\Enums\CrmTagColor;
use Database\Factories\CrmTagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CrmTag extends Model
{
    /** @use HasFactory<CrmTagFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'color',
    ];

    protected static function booted(): void
    {
        static::saving(function (CrmTag $tag): void {
            $tag->slug = Str::slug($tag->name);
        });
    }

    /**
     * @return MorphToMany<CrmAccount, $this>
     */
    public function accounts(): MorphToMany
    {
        return $this->morphedByMany(CrmAccount::class, 'taggable', 'crm_taggables');
    }

    /**
     * Nombre de comptes étiquetés, à afficher dans les listes et les
     * confirmations de suppression.
     */
    public function accountsCount(): int
    {
        return $this->accounts()->count();
    }

    /**
     * Nom de couleur Filament, avec repli sur le gris pour les valeurs
     * historiques hors palette.
     */
    public function badgeColor(): string
    {
        return CrmTagColor::tryFrom((string) $this->color)?->getColor()
            ?? CrmTagColor::Gray->getColor();
    }

    /**
     * Rapproche deux libellés indépendamment de la casse, des accents, de la
     * ponctuation et du pluriel, puis tolère une faute de frappe d'un
     * caractère.
     *
     * Les libellés trop courts ne sont jamais comparés : à trois lettres, la
     * distance d'édition sépare des sigles réellement différents.
     */
    public static function areLikelyDuplicates(self $a, self $b): bool
    {
        $keyA = self::normalizeKey($a->name);
        $keyB = self::normalizeKey($b->name);

        if ($keyA === $keyB) {
            return true;
        }

        if (mb_strlen($keyA) < 5 || mb_strlen($keyB) < 5) {
            return false;
        }

        return levenshtein($keyA, $keyB) <= 1;
    }

    /**
     * Clé de comparaison : mots en minuscules, sans accent ni ponctuation,
     * débarrassés d'une terminaison plurielle.
     */
    public static function normalizeKey(string $name): string
    {
        $words = preg_split('/[^a-z0-9]+/i', Str::ascii($name)) ?: [];

        $normalized = array_map(
            static function (string $word): string {
                $word = Str::lower($word);

                if (mb_strlen($word) < 5) {
                    return $word;
                }

                foreach (['es', 's', 'x'] as $suffix) {
                    $stem = mb_substr($word, 0, -mb_strlen($suffix));

                    if (mb_strlen($stem) >= 4 && str_ends_with($word, $suffix)) {
                        return $stem;
                    }
                }

                return $word;
            },
            array_filter($words, static fn (string $word): bool => $word !== ''),
        );

        return implode('', $normalized);
    }

    /**
     * Regroupe les étiquettes qui semblent désigner la même chose.
     *
     * @return list<list<self>>
     */
    public static function duplicateGroups(): array
    {
        /** @var list<self> $tags */
        $tags = self::query()->orderBy('id')->get()->all();

        $groups = [];
        $claimed = [];

        foreach ($tags as $index => $tag) {
            if (isset($claimed[$tag->getKey()])) {
                continue;
            }

            $group = [$tag];

            foreach (array_slice($tags, $index + 1) as $candidate) {
                if (isset($claimed[$candidate->getKey()])) {
                    continue;
                }

                if (! self::areLikelyDuplicates($tag, $candidate)) {
                    continue;
                }

                $group[] = $candidate;
                $claimed[$candidate->getKey()] = true;
            }

            if (count($group) < 2) {
                continue;
            }

            $claimed[$tag->getKey()] = true;
            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * @return array<int, true>
     */
    public static function duplicateKeyMap(): array
    {
        $map = [];

        foreach (self::duplicateGroups() as $group) {
            foreach ($group as $tag) {
                $map[$tag->getKey()] = true;
            }
        }

        return $map;
    }

    /**
     * @param  list<self>  $group
     */
    public static function cleanupSurvivor(array $group): self
    {
        $survivor = collect($group)
            ->sortBy(fn (self $tag): int => $tag->getKey())
            ->first();

        if (! $survivor instanceof self) {
            throw new InvalidArgumentException('Un groupe de nettoyage doit contenir au moins une étiquette.');
        }

        return $survivor;
    }
}
