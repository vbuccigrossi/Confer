<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\User;
use App\Models\Reaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reaction>
 */
class ReactionFactory extends Factory
{
    protected $model = Reaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $emojis = ['👍', '❤️', '😂', '🎉', '🚀', '👀', '🔥', '✅'];

        return [
            'message_id' => Message::factory(),
            'user_id' => User::factory(),
            'emoji' => $this->faker->randomElement($emojis),
        ];
    }
}
