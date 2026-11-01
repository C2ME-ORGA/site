<?php

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectImage;
use Database\Seeders\ProjectDemoSeeder;
use Illuminate\Support\Facades\Storage;

test('le seeder des réalisations crée les départements, chantiers et photos', function (): void {
    Storage::fake('public');

    $this->seed(ProjectDemoSeeder::class);

    $expectedPhotos = collect(glob(public_path('images/realisations/*/photo-*.webp')) ?: [])
        ->reject(fn (string $file): bool => str_contains(basename($file), '-thumb'))
        ->count();

    expect(ProjectCategory::query()->count())->toBe(4)
        ->and(Project::query()->count())->toBe(14)
        ->and(ProjectImage::query()->count())->toBe($expectedPhotos)
        ->and(Project::query()->published()->count())->toBe(14);
});

test('le seeder des réalisations est idempotent', function (): void {
    Storage::fake('public');

    $this->seed(ProjectDemoSeeder::class);

    $before = [
        ProjectCategory::query()->count(),
        Project::query()->count(),
        ProjectImage::query()->count(),
    ];

    $this->seed(ProjectDemoSeeder::class);

    $after = [
        ProjectCategory::query()->count(),
        Project::query()->count(),
        ProjectImage::query()->count(),
    ];

    expect($after)->toBe($before);
});
