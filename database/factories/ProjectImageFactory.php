<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectImage>
 */
class ProjectImageFactory extends Factory
{
    protected $model = ProjectImage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'path' => 'projects/'.$this->faker->uuid().'.webp',
            'thumb_path' => null,
            'alt' => 'Réalisation C2ME — '.$this->faker->sentence(3),
            'position' => 0,
        ];
    }
}
