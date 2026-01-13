<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Feature__Files__BasicUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('test.png', 100, 100)->size(100);

        $response = $this->actingAs($user)->postJson('/api/files', [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'file_name',
                'mime_type',
                'size_bytes',
            ]);

        $this->assertDatabaseHas('attachments', [
            'uploader_id' => $user->id,
        ]);
    }

    public function test_file_metadata_correct(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->actingAs($user)->postJson('/api/files', [
            'file' => $file,
        ]);

        $response->assertStatus(201);

        $attachment = Attachment::find($response->json('id'));

        $this->assertNotNull($attachment);
        $this->assertEquals('photo.jpg', $attachment->file_name);
        $this->assertStringContainsString('image/', $attachment->mime_type);
    }

    public function test_user_can_delete_own_attachment(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('delete-me.png');

        $uploadResponse = $this->actingAs($user)->postJson('/api/files', [
            'file' => $file,
        ]);

        $attachmentId = $uploadResponse->json('id');

        $response = $this->actingAs($user)->deleteJson("/api/files/{$attachmentId}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('attachments', ['id' => $attachmentId]);
    }
}
