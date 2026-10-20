<?php

namespace Database\Factories;

use App\Models\EmailMailbox;
use App\Models\EmailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailMessage>
 */
class EmailMessageFactory extends Factory
{
    protected $model = EmailMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_mailbox_id' => EmailMailbox::factory(),
            'message_id' => $this->faker->unique()->bothify('?????@example.test'),
            'folder' => 'INBOX',
            'uid' => (string) $this->faker->unique()->numberBetween(1, 99999),
            'from_email' => $this->faker->safeEmail(),
            'from_name' => $this->faker->name(),
            'to_recipients' => [['email' => 'commercial@c2me.fr', 'name' => null]],
            'cc_recipients' => [],
            'subject' => $this->faker->sentence(4),
            'body_text' => $this->faker->paragraph(),
            'received_at' => now(),
            'is_read' => false,
            'is_sent' => false,
        ];
    }

    public function read(): static
    {
        return $this->state(fn (): array => ['is_read' => true]);
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'folder' => 'Sent',
            'is_read' => true,
            'is_sent' => true,
            'sent_at' => now(),
        ]);
    }
}
