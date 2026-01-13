<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->words(2, true);
        
        return [
            'workspace_id' => Workspace::factory(),
            'type' => Conversation::TYPE_PUBLIC_CHANNEL,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 9999),
            'topic' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'created_by' => User::factory(),
            'is_archived' => false,
        ];
    }

    public function privateChannel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Conversation::TYPE_PRIVATE_CHANNEL,
        ]);
    }

    public function directMessage(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Conversation::TYPE_DM,
            'name' => null,
            'slug' => null,
        ]);
    }

    public function groupDM(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Conversation::TYPE_GROUP_DM,
            'name' => null,
            'slug' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_archived' => true,
            'archived_at' => now(),
        ]);
    }
}
