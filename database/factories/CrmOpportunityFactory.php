<?php

namespace Database\Factories;

use App\Enums\OpportunityStage;
use App\Models\CrmAccount;
use App\Models\CrmOpportunity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmOpportunity>
 */
class CrmOpportunityFactory extends Factory
{
    protected $model = CrmOpportunity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $stage = $this->faker->randomElement(OpportunityStage::openCases());

        return [
            'crm_account_id' => CrmAccount::factory(),
            'title' => $this->faker->sentence(4),
            'stage' => $stage,
            'amount_estimated' => $this->faker->randomFloat(2, 5000, 250000),
            'probability' => $stage->defaultProbability(),
            'expected_close_date' => $this->faker->dateTimeBetween('now', '+6 months'),
        ];
    }
}
