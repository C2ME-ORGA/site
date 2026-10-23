<?php

namespace Database\Factories;

use App\Enums\CrmTagColor;
use App\Models\CrmTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmTag>
 */
class CrmTagFactory extends Factory
{
    protected $model = CrmTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'color' => $this->faker->randomElement(array_keys(CrmTagColor::options())),
        ];
    }
}
