<?php

namespace Database\Factories;

use App\Enums\QuoteDocumentStatus;
use App\Models\CrmAccount;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crm_account_id' => CrmAccount::factory(),
            'status' => QuoteDocumentStatus::Draft,
            'subject' => $this->faker->sentence(4),
            'issued_at' => now(),
            'valid_until' => now()->addDays(7),
            'amount_ht' => $this->faker->randomFloat(2, 500, 50000),
            'vat_rate' => 0,
        ];
    }
}
