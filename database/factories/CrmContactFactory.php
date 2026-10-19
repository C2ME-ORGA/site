<?php

namespace Database\Factories;

use App\Models\CrmAccount;
use App\Models\CrmContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmContact>
 */
class CrmContactFactory extends Factory
{
    protected $model = CrmContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crm_account_id' => CrmAccount::factory(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'job_title' => $this->faker->jobTitle(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'is_primary' => false,
            'decision_maker' => false,
            'do_not_contact' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => [
            'is_primary' => true,
            'decision_maker' => true,
        ]);
    }
}
