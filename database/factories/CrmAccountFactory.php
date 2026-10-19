<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\CrmBook;
use App\Enums\CrmCategory;
use App\Models\CrmAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrmAccount>
 */
class CrmAccountFactory extends Factory
{
    protected $model = CrmAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = $this->faker->randomElement(CrmCategory::cases());

        return [
            'book' => $category->book(),
            'category' => $category,
            'name' => $this->faker->company(),
            'legal_name' => $this->faker->company(),
            'siret' => $this->faker->numerify('##############'),
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address_line1' => $this->faker->streetAddress(),
            'postal_code' => $this->faker->postcode(),
            'city' => $this->faker->city(),
            'country' => 'FR',
            'status' => AccountStatus::Prospect,
            'metadata' => [],
        ];
    }

    public function forBook(CrmBook $book): static
    {
        $category = $this->faker->randomElement(CrmCategory::forBook($book));

        return $this->state(fn (): array => [
            'book' => $book,
            'category' => $category,
        ]);
    }
}
