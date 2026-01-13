<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InviteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'email' => fake()->unique()->safeEmail(),
            'token' => Str::random(64),
            'invited_by' => User::factory(),
            'role' => fake()->randomElement(['admin', 'member']),
            'accepted_at' => null,
            'expires_at' => now()->addDays(7),
        ];
    }
}
