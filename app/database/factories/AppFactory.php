<?php
namespace Database\Factories;
use App\Models\App;
use App\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class AppFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => $this->faker->words(2, true),
            'type' => App::TYPE_WEBHOOK,
            'client_id' => App::generateClientId(),
            'client_secret' => Hash::make('secret'),
            'token' => Hash::make('token'),
            'scopes' => [],
            'callback_url' => $this->faker->url(),
            'default_conversation_id' => null,
            'created_by' => User::factory(),
            'is_active' => true,
        ];
    }

    public function webhook(): static
    {
        return $this->state(fn (array $attr) => ['type' => App::TYPE_WEBHOOK]);
    }

    public function bot(): static
    {
        return $this->state(fn (array $attr) => [
            'type' => App::TYPE_BOT,
            'scopes' => [App::SCOPE_CHAT_WRITE],
        ]);
    }

    public function slash(): static
    {
        return $this->state(fn (array $attr) => ['type' => App::TYPE_SLASH]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attr) => ['is_active' => false]);
    }
}
