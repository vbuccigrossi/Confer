<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bodyMd = $this->faker->sentence(10);

        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'parent_message_id' => null,
            'body_md' => $bodyMd,
            'body_html' => '<p>' . htmlspecialchars($bodyMd) . '</p>', // Will be overwritten by boot()
            'edited_at' => null,
            'is_system' => false,
        ];
    }

    /**
     * Indicate that the message is a system message.
     */
    public function system()
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
            'user_id' => null,
        ]);
    }

    /**
     * Indicate that the message is a thread reply.
     */
    public function thread()
    {
        return $this->state(fn (array $attributes) => [
            'parent_message_id' => Message::factory(),
        ]);
    }

    /**
     * Indicate that the message has been edited.
     */
    public function edited()
    {
        return $this->state(fn (array $attributes) => [
            'edited_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }
}
