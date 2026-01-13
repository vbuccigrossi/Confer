<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workspace = Workspace::factory()->create();
        $conversation = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
        ]);
        $actor = User::factory()->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $actor->id,
        ]);

        return [
            'workspace_id' => $workspace->id,
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement([
                Notification::TYPE_MENTION,
                Notification::TYPE_THREAD_REPLY,
            ]),
            'actor_user_id' => $actor->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'payload' => [
                'message_preview' => $this->faker->sentence(),
            ],
            'is_read' => false,
        ];
    }

    /**
     * Indicate that the notification is a mention.
     */
    public function mention(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Notification::TYPE_MENTION,
        ]);
    }

    /**
     * Indicate that the notification is a thread reply.
     */
    public function threadReply(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Notification::TYPE_THREAD_REPLY,
        ]);
    }

    /**
     * Indicate that the notification is read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => true,
        ]);
    }

    /**
     * Indicate that the notification is unread.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_read' => false,
        ]);
    }
}
