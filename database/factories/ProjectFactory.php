<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_category_id' => ProjectCategory::factory(),
            'title' => $this->faker->unique()->sentence(3),
            'client' => $this->faker->company(),
            'location' => $this->faker->city(),
            'year' => (int) now()->year,
            'description' => $this->faker->paragraphs(2, true),
            'services' => $this->faker->randomElements(
                ['Charpente métal', 'Bardage métal', 'Couverture', 'Conception-réalisation', 'Métallerie'],
                2,
            ),
            'is_published' => true,
            'position' => 0,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
