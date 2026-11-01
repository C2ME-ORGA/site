<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Jeu de démonstration des réalisations, construit à partir des photos
 * présentes dans public/images/realisations (un dossier par chantier).
 *
 * Idempotent : les départements et chantiers existants sont réutilisés, et
 * les photos ne sont ajoutées que si le chantier n'en a pas encore.
 */
class ProjectDemoSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, description: string}>
     */
    private const DEPARTMENTS = [
        'industriel' => [
            'name' => 'Bâtiments industriels',
            'description' => 'Hangars, ateliers et entrepôts de production.',
        ],
        'agricole' => [
            'name' => 'Bâtiments agricoles',
            'description' => 'Stabulations, hangars et bâtiments d’élevage.',
        ],
        'tertiaire' => [
            'name' => 'Tertiaire et commerces',
            'description' => 'Locaux commerciaux, restaurants et bureaux.',
        ],
        'mobilite' => [
            'name' => 'Ouvrages d’art et mobilités',
            'description' => 'Passerelles, parkings et ouvrages de mobilité.',
        ],
    ];

    /**
     * Chantiers de démonstration, par dossier de public/images/realisations.
     *
     * @var array<string, array{title: string, department: string, client: string, location: string, year: int, description: string}>
     */
    private const PROJECTS = [
        '3P' => [
            'title' => 'Hangar 3P',
            'department' => 'industriel',
            'client' => '3P',
            'location' => 'Montaigu-Vendée (85)',
            'year' => 2024,
            'description' => 'Hangar de stockage de 1 800 m² : charpente, bardage et couverture fabriqués en atelier puis montés sur site.',
        ],
        'Albi' => [
            'title' => 'Hangar agricole d’Albi',
            'department' => 'agricole',
            'client' => 'GAEC d’Albi',
            'location' => 'Albi (81)',
            'year' => 2023,
            'description' => 'Bâtiment agricole de 1 200 m² pour le stockage de fourrage, avec charpente métallique et couverture bac acier.',
        ],
        'bbq' => [
            'title' => 'Restaurant BBQ',
            'department' => 'tertiaire',
            'client' => 'BBQ',
            'location' => 'Nantes (44)',
            'year' => 2025,
            'description' => 'Extension d’un restaurant : ossature métallique, plancher et garde-corps réalisés sur mesure.',
        ],
        'Bordeau' => [
            'title' => 'Atelier Bordeau',
            'department' => 'industriel',
            'client' => 'Bordeau',
            'location' => 'La Roche-sur-Yon (85)',
            'year' => 2024,
            'description' => 'Atelier de fabrication de 900 m², avec bardage métallique et ouvertures pour pont roulant.',
        ],
        'constans' => [
            'title' => 'Stabulation Constans',
            'department' => 'agricole',
            'client' => 'EARL Constans',
            'location' => 'Cholet (49)',
            'year' => 2023,
            'description' => 'Stabulation bovine de 1 500 m² : charpente métallique, couverture et bardage ventilé.',
        ],
        'dussac' => [
            'title' => 'Bâtiment d’élevage Dussac',
            'department' => 'agricole',
            'client' => 'Dussac',
            'location' => 'Les Herbiers (85)',
            'year' => 2022,
            'description' => 'Hangar d’élevage de 800 m², conçu pour résister aux vents de la Vendée intérieure.',
        ],
        'Escala' => [
            'title' => 'Salle Escala',
            'department' => 'tertiaire',
            'client' => 'Escala',
            'location' => 'Angers (49)',
            'year' => 2025,
            'description' => 'Salle de réception et locaux techniques : charpente métallique de grande portée et métallerie.',
        ],
        'Lacroix' => [
            'title' => 'Usine Lacroix',
            'department' => 'industriel',
            'client' => 'Lacroix',
            'location' => 'Saint-Nazaire (44)',
            'year' => 2024,
            'description' => 'Extension d’une usine de 2 400 m² : ossature, bardage et couverture pour une ligne de production.',
        ],
        'Minaudiere' => [
            'title' => 'Résidence Minaudière',
            'department' => 'tertiaire',
            'client' => 'Minaudière',
            'location' => 'Tours (37)',
            'year' => 2023,
            'description' => 'Résidence services : planchers collaborants, charpente métallique et garde-corps.',
        ],
        'Monestre' => [
            'title' => 'Entrepôt Monestre',
            'department' => 'industriel',
            'client' => 'Monestre',
            'location' => 'Rennes (35)',
            'year' => 2025,
            'description' => 'Entrepôt logistique de 3 000 m², avec charpente métallique et bardage isolé.',
        ],
        'protextyl' => [
            'title' => 'Atelier Protextyl',
            'department' => 'industriel',
            'client' => 'Protextyl',
            'location' => 'Laval (53)',
            'year' => 2024,
            'description' => 'Atelier de production de 1 100 m², charpente et couverture adaptées aux contraintes de process.',
        ],
        'Soucha' => [
            'title' => 'Hangar Soucha',
            'department' => 'agricole',
            'client' => 'Soucha',
            'location' => 'Fontenay-le-Comte (85)',
            'year' => 2022,
            'description' => 'Hangar agricole de 700 m² pour le matériel, avec charpente métallique et bardage.',
        ],
        'valastro' => [
            'title' => 'Bâtiment Valastro',
            'department' => 'tertiaire',
            'client' => 'Valastro',
            'location' => 'Bordeaux (33)',
            'year' => 2025,
            'description' => 'Local commercial et bureaux : structure métallique, façade vitrée et métallerie sur mesure.',
        ],
        'verges' => [
            'title' => 'Parking Verges',
            'department' => 'mobilite',
            'client' => 'Verges',
            'location' => 'Vergèze (30)',
            'year' => 2023,
            'description' => 'Parking de 200 places : charpente métallique, planchers et garde-corps pour un ouvrage de mobilité.',
        ],
    ];

    public function run(): void
    {
        $categories = $this->seedDepartments();

        $projects = 0;
        $photos = 0;
        $position = 0;

        foreach (self::PROJECTS as $folder => $data) {
            $project = $this->seedProject($folder, $data, $categories[$data['department']] ?? null, $position);

            if ($project === null) {
                continue;
            }

            $photos += $this->seedPhotos($project, $folder, $data['title']);
            $projects++;
            $position++;
        }

        $this->command?->info(sprintf(
            'Réalisations de démonstration : %d départements, %d chantiers, %d photos.',
            count($categories),
            $projects,
            $photos,
        ));
    }

    /**
     * @return array<string, ProjectCategory>
     */
    private function seedDepartments(): array
    {
        $categories = [];

        foreach (self::DEPARTMENTS as $key => $department) {
            $categories[$key] = ProjectCategory::query()->where('name', $department['name'])->first()
                ?? ProjectCategory::create([
                    'name' => $department['name'],
                    'description' => $department['description'],
                    'position' => array_search($key, array_keys(self::DEPARTMENTS), true) ?: 0,
                    'is_active' => true,
                ]);
        }

        return $categories;
    }

    /**
     * @param  array{title: string, department: string, client: string, location: string, year: int, description: string}  $data
     */
    private function seedProject(string $folder, array $data, ?ProjectCategory $category, int $position): ?Project
    {
        if (! is_dir(public_path('images/realisations/'.$folder))) {
            return null;
        }

        return Project::query()->firstOrCreate(
            ['slug' => Str::slug(str_replace(['’', "'"], ' ', $data['title']))],
            [
                'project_category_id' => $category?->id,
                'title' => $data['title'],
                'client' => $data['client'],
                'location' => $data['location'],
                'year' => $data['year'],
                'description' => $data['description'],
                'services' => $this->servicesFor($data['department']),
                'is_published' => true,
                'position' => $position,
            ],
        );
    }

    /**
     * Copie les photos du dossier source sur le disque public et crée les
     * entrées correspondantes. Ne fait rien si le chantier a déjà des photos.
     */
    private function seedPhotos(Project $project, string $folder, string $title): int
    {
        if ($project->images()->exists()) {
            return 0;
        }

        $files = glob(public_path("images/realisations/{$folder}/photo-*.webp")) ?: [];

        $files = array_values(array_filter(
            $files,
            fn (string $file): bool => ! str_contains(basename($file), '-thumb'),
        ));

        sort($files);

        $disk = Storage::disk('public');
        $created = 0;

        foreach ($files as $index => $file) {
            $target = sprintf('projects/%s-%02d.webp', $project->slug, $index + 1);

            if (! $disk->exists($target)) {
                $contents = file_get_contents($file);

                if ($contents === false) {
                    continue;
                }

                $disk->put($target, $contents);
            }

            ProjectImage::create([
                'project_id' => $project->id,
                'path' => $target,
                'alt' => 'Réalisation C2ME — '.$title,
                'position' => $index,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * @return list<string>
     */
    private function servicesFor(string $department): array
    {
        return match ($department) {
            'agricole' => ['Charpente métal', 'Couverture', 'Bardage métal'],
            'tertiaire' => ['Conception-réalisation', 'Charpente métal', 'Métallerie'],
            'mobilite' => ['Charpente métal', 'Plancher', 'Métallerie'],
            default => ['Charpente métal', 'Bardage métal', 'Couverture'],
        };
    }
}
