<?php

namespace Database\Factories;

use App\Models\EmailMailbox;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailMailbox>
 */
class EmailMailboxFactory extends Factory
{
    protected $model = EmailMailbox::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = $this->faker->unique()->safeEmail();

        return [
            'name' => 'Commercial',
            'email' => $email,
            'is_shared' => true,
            'imap_host' => 'imap.orange.fr',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => $email,
            'imap_password' => 'secret',
            'smtp_host' => 'smtp.orange.fr',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => $email,
            'smtp_password' => 'secret',
            'is_active' => true,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'is_shared' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
