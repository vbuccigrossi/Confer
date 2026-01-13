<?php
namespace Database\Factories;
use App\Models\AuditLog;
use App\Models\App;
use App\Models\Workspace;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'app_id' => App::factory(),
            'user_id' => User::factory(),
            'action' => AuditLog::ACTION_WEBHOOK_POSTED,
            'subject_type' => null,
            'subject_id' => null,
            'metadata' => [],
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }
}
