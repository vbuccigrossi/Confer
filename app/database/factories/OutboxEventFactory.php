<?php
namespace Database\Factories;
use App\Models\OutboxEvent;
use App\Models\App;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutboxEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'app_id' => App::factory(),
            'event_type' => OutboxEvent::EVENT_TYPE_SLASH_COMMAND,
            'payload' => ['command' => 'test', 'text' => 'hello'],
            'delivery_status' => OutboxEvent::STATUS_PENDING,
            'attempt_count' => 0,
            'last_attempt_at' => null,
            'last_error' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attr) => ['delivery_status' => OutboxEvent::STATUS_PENDING]);
    }

    public function success(): static
    {
        return $this->state(fn (array $attr) => ['delivery_status' => OutboxEvent::STATUS_SUCCESS]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attr) => [
            'delivery_status' => OutboxEvent::STATUS_FAILED,
            'last_error' => 'Connection timeout',
        ]);
    }
}
