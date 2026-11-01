<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Chantier publié sur la page « Nos réalisations ».
 *
 * @property int $id
 * @property int|null $project_category_id
 * @property string $title
 * @property string $slug
 * @property string|null $client
 * @property string|null $location
 * @property int|null $year
 * @property string|null $description
 * @property list<string>|null $services
 * @property bool $is_published
 * @property int $position
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_category_id',
        'title',
        'slug',
        'client',
        'location',
        'year',
        'description',
        'services',
        'is_published',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'services' => 'array',
            'is_published' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Project $project): void {
            $project->slug = Str::slug(str_replace(['’', "'"], ' ', $project->title));
        });
    }

    /**
     * @return BelongsTo<ProjectCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'project_category_id');
    }

    /**
     * @return HasMany<ProjectImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProjectImage::class)->orderBy('position')->orderBy('id');
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderByDesc('year')->orderBy('title');
    }

    public function cover(): ?ProjectImage
    {
        return $this->images->first();
    }

    public function excerpt(int $limit = 160): string
    {
        return Str::limit(trim((string) $this->description), $limit);
    }

    public function url(): string
    {
        return route('projects.show', $this);
    }
}
