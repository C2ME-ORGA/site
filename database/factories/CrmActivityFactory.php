<?php

namespace Database\Factories;

use App\Enums\CrmActivityType;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmActivity>
 */
class CrmActivityFactory extends Factory
{
    protected $model = CrmActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(CrmActivityType::cases());

        return [
            'crm_contact_id' => CrmContact::factory(),
            'type' => $type,
            'subject' => $this->faker->sentence(5),
            'body' => $this->faker->paragraph(),
            'occurred_at' => $type->isPlanned() ? null : $this->faker->dateTimeBetween('-1 month'),
            'due_at' => $type->isPlanned() ? $this->faker->dateTimeBetween('now', '+1 month') : null,
        ];
    }
}
