<?php

use App\Enums\UserRole;
use App\Filament\Resources\ProjectCategories\Pages\CreateProjectCategory;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function projectAdmin(): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'is_active' => true,
    ]);
}

test('la gestion des réalisations est réservée aux administrateurs', function (): void {
    $commercial = User::factory()->create([
        'role' => UserRole::Commercial,
        'is_active' => true,
    ]);

    $this->actingAs($commercial)
        ->get('/admin/projects')
        ->assertForbidden();
});

test('un administrateur crée un département et un chantier', function (): void {
    $admin = projectAdmin();
    $category = ProjectCategory::factory()->create(['name' => 'Bâtiments industriels']);

    Livewire::actingAs($admin)
        ->test(CreateProjectCategory::class)
        ->fillForm([
            'name' => 'Ouvrages d’art',
            'description' => 'Passerelles et ponts.',
            'position' => 3,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $createdCategory = ProjectCategory::query()->where('name', 'Ouvrages d’art')->sole();

    expect($createdCategory->slug)->toBe('ouvrages-d-art');

    Livewire::actingAs($admin)
        ->test(CreateProject::class)
        ->fillForm([
            'project_category_id' => $category->id,
            'title' => 'Hangar Test',
            'client' => 'Client Test',
            'location' => 'Nantes (44)',
            'year' => 2026,
            'services' => ['Charpente métal'],
            'description' => 'Description du chantier.',
            'is_published' => true,
            'position' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $project = Project::query()->sole();

    expect($project->title)->toBe('Hangar Test')
        ->and($project->slug)->toBe('hangar-test')
        ->and($project->services)->toBe(['Charpente métal'])
        ->and($project->category->is($category))->toBeTrue();
});

test('une vignette WebP est générée à l’enregistrement d’une photo', function (): void {
    Storage::fake('public');

    Storage::disk('public')->put(
        'projects/photo.jpg',
        (string) UploadedFile::fake()->image('photo.jpg', 1600, 900)->getContent(),
    );

    $project = Project::factory()->create();

    $image = ProjectImage::factory()->create([
        'project_id' => $project->id,
        'path' => 'projects/photo.jpg',
    ]);

    expect($image->refresh()->thumb_path)->toBe('projects/thumbs/photo.webp')
        ->and(Storage::disk('public')->exists('projects/thumbs/photo.webp'))->toBeTrue();
});

test('la suppression d’une photo retire ses fichiers', function (): void {
    Storage::fake('public');

    Storage::disk('public')->put('projects/photo.webp', 'image');
    Storage::disk('public')->put('projects/thumbs/photo.webp', 'vignette');

    $image = ProjectImage::factory()->create([
        'path' => 'projects/photo.webp',
        'thumb_path' => 'projects/thumbs/photo.webp',
    ]);

    $image->delete();

    Storage::disk('public')->assertMissing('projects/photo.webp');
    Storage::disk('public')->assertMissing('projects/thumbs/photo.webp');
});
