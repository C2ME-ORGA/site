<?php

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectImage;

test('la page réalisations liste les chantiers publiés', function (): void {
    $category = ProjectCategory::factory()->create(['name' => 'Bâtiments industriels']);
    $published = Project::factory()->create([
        'project_category_id' => $category->id,
        'title' => 'Hangar Test',
    ]);

    ProjectImage::factory()->count(2)->create(['project_id' => $published->id]);

    Project::factory()->unpublished()->create(['title' => 'Chantier caché']);

    $this->get('/realisations')
        ->assertOk()
        ->assertSee('Hangar Test')
        ->assertSee('Bâtiments industriels')
        ->assertDontSee('Chantier caché');
});

test('le filtre par département restreint la liste des chantiers', function (): void {
    $industry = ProjectCategory::factory()->create(['name' => 'Industriel']);
    $agriculture = ProjectCategory::factory()->create(['name' => 'Agricole']);

    $industryProject = Project::factory()->create([
        'project_category_id' => $industry->id,
        'title' => 'Bâtiment industriel',
    ]);
    $agricultureProject = Project::factory()->create([
        'project_category_id' => $agriculture->id,
        'title' => 'Hangar agricole',
    ]);

    $this->get('/realisations?departement='.$industry->slug)
        ->assertOk()
        ->assertSee($industryProject->title)
        ->assertDontSee($agricultureProject->title);

    $this->get('/realisations?departement='.$agriculture->slug)
        ->assertOk()
        ->assertSee($agricultureProject->title)
        ->assertDontSee($industryProject->title);
});

test('la fiche chantier affiche les photos et les prestations', function (): void {
    $project = Project::factory()->create([
        'title' => 'Hangar Test',
        'description' => 'Une charpente de 1 200 m².',
        'services' => ['Charpente métal', 'Bardage métal'],
    ]);

    ProjectImage::factory()->create([
        'project_id' => $project->id,
        'path' => 'projects/photo.webp',
        'alt' => 'Façade du hangar',
    ]);

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Hangar Test')
        ->assertSee('Charpente métal')
        ->assertSee('Bardage métal')
        ->assertSee('Façade du hangar')
        ->assertSee('Photos du chantier');
});

test('un chantier non publié renvoie une page introuvable', function (): void {
    $project = Project::factory()->unpublished()->create();

    $this->get(route('projects.show', $project))->assertNotFound();
});

test('le slug du chantier est généré depuis son titre', function (): void {
    $project = Project::factory()->create(['title' => 'Hangar Agricole de la Vendée']);

    expect($project->slug)->toBe('hangar-agricole-de-la-vendee');
});

test('le sitemap inclut les chantiers publiés uniquement', function (): void {
    $published = Project::factory()->create(['title' => 'Chantier publié']);
    $hidden = Project::factory()->unpublished()->create(['title' => 'Chantier caché']);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee(route('projects.show', $published), escape: false)
        ->assertDontSee(route('projects.show', $hidden), escape: false);
});
