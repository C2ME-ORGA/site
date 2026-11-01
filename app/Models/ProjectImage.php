<?php

namespace App\Models;

use Database\Factories\ProjectImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Photo d'un chantier, stockée sur le disque public avec une vignette WebP
 * générée à l'enregistrement (grille de la page Réalisations).
 *
 * @property int $id
 * @property int $project_id
 * @property string $path
 * @property string|null $thumb_path
 * @property string|null $alt
 * @property int $position
 */
class ProjectImage extends Model
{
    /** @use HasFactory<ProjectImageFactory> */
    use HasFactory;

    /**
     * Largeur maximale de la vignette affichée dans la grille publique.
     */
    private const THUMBNAIL_WIDTH = 720;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'path',
        'thumb_path',
        'alt',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (ProjectImage $image): void {
            if ($image->wasRecentlyCreated || $image->wasChanged('path')) {
                $image->generateThumbnail();
            }
        });

        static::deleted(function (ProjectImage $image): void {
            $image->deleteFiles();
        });
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function thumbUrl(): string
    {
        return Storage::disk('public')->url($this->thumb_path ?? $this->path);
    }

    /**
     * Génère une vignette WebP à partir de l'image déposée. Silencieux si
     * l'extension GD est absente ou si le fichier n'est pas une image lisible :
     * la vue publique retombe alors sur l'image d'origine.
     */
    public function generateThumbnail(): void
    {
        $disk = Storage::disk('public');

        if (! $disk->exists($this->path) || ! function_exists('imagecreatefromstring')) {
            return;
        }

        $source = @imagecreatefromstring($disk->get($this->path));

        if ($source === false) {
            return;
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $targetWidth = min(self::THUMBNAIL_WIDTH, $width);
        $targetHeight = max(1, (int) round($height * ($targetWidth / $width)));

        $thumbnail = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($thumbnail === false) {
            imagedestroy($source);

            return;
        }

        imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $path = $this->thumbnailPathFor($this->path);
        $temporary = tempnam(sys_get_temp_dir(), 'c2me-thumb-');

        if ($temporary !== false && imagewebp($thumbnail, $temporary, 82)) {
            $disk->put($path, (string) file_get_contents($temporary));
        }

        if ($temporary !== false) {
            @unlink($temporary);
        }

        imagedestroy($thumbnail);
        imagedestroy($source);

        $this->forceFill(['thumb_path' => $path])->saveQuietly();
    }

    public function deleteFiles(): void
    {
        $disk = Storage::disk('public');

        $disk->delete($this->path);

        if ($this->thumb_path !== null) {
            $disk->delete($this->thumb_path);
        }
    }

    private function thumbnailPathFor(string $path): string
    {
        return 'projects/thumbs/'.pathinfo($path, PATHINFO_FILENAME).'.webp';
    }
}
