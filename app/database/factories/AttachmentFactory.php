<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'message_id' => Message::factory(),
            'uploader_id' => User::factory(),
            'storage_path' => 'uploads/test/' . fake()->uuid() . '.jpg',
            'disk' => 'local',
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(1024, 1024 * 1024),
            'file_name' => fake()->word() . '.jpg',
            'sha256' => hash('sha256', fake()->text()),
        ];
    }
}
