<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Feature__Files__UploadDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
        $this->workspace->members()->create(['user_id' => $this->user->id, 'role' => 'owner']);

        $this->conversation = Conversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'type' => Conversation::TYPE_PUBLIC_CHANNEL,
            'created_by' => $this->user->id,
        ]);
        $this->conversation->members()->create(['user_id' => $this->user->id, 'role' => 'member']);
    }

    public function test_user_can_upload_file(): void
    {
        $file = UploadedFile::fake()->image('test.png', 100, 100)->size(100);

        $response = $this->actingAs($this->user)->postJson('/api/files', [
            'file' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'file_name',
                'mime_type',
                'size_bytes',
                'size_human',
                'url',
                'thumbnail_url',
            ]);

        $this->assertDatabaseHas('attachments', [
            'uploader_id' => $this->user->id,
            'file_name' => 'test.png',
        ]);
    }

    public function test_file_too_large_rejected(): void
    {
        // Create a 100MB file (exceeds default 64MB limit)
        $file = UploadedFile::fake()->create('large.pdf', 100 * 1024);

        $response = $this->actingAs($this->user)->postJson('/api/files', [
            'file' => $file,
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_download_own_file(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $uploadResponse = $this->actingAs($this->user)->postJson('/api/files', [
            'file' => $file,
        ]);

        $signedUrl = $uploadResponse->json('url');

        // Download the file using signed URL
        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'image/jpeg');
    }

    public function test_conversation_member_can_download_attached_file(): void
    {
        $otherUser = User::factory()->create();
        $this->workspace->members()->create(['user_id' => $otherUser->id, 'role' => 'member']);
        $this->conversation->members()->create(['user_id' => $otherUser->id, 'role' => 'member']);

        $message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
        ]);

        $file = UploadedFile::fake()->image('shared.png');

        $uploadResponse = $this->actingAs($this->user)->postJson('/api/files', [
            'file' => $file,
            'message_id' => $message->id,
        ]);

        $attachment = Attachment::find($uploadResponse->json('id'));
        $signedUrl = $attachment->getSignedUrl();

        // Other conversation member can download
        $response = $this->actingAs($otherUser)->get($signedUrl);

        $response->assertStatus(200);
    }

    public function test_non_member_cannot_download_attached_file(): void
    {
        $nonMember = User::factory()->create();

        $message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
        ]);

        $file = UploadedFile::fake()->image('private.png');

        $uploadResponse = $this->actingAs($this->user)->postJson('/api/files', [
            'file' => $file,
            'message_id' => $message->id,
        ]);

        $attachment = Attachment::find($uploadResponse->json('id'));
        $signedUrl = $attachment->getSignedUrl();

        // Non-member cannot download
        $response = $this->actingAs($nonMember)->get($signedUrl);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_own_file(): void
    {
        $file = UploadedFile::fake()->image('deleteme.png');

        $uploadResponse = $this->actingAs($this->user)->postJson('/api/files', [
            'file' => $file,
        ]);

        $attachmentId = $uploadResponse->json('id');

        $response = $this->actingAs($this->user)->deleteJson("/api/files/{$attachmentId}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('attachments', ['id' => $attachmentId]);
    }

    public function test_thumbnail_generated_for_images(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 1000, 1000);

        $response = $this->actingAs($this->user)->postJson('/api/files', [
            'file' => $file,
        ]);

        $response->assertStatus(201);

        $attachment = Attachment::find($response->json('id'));

        $this->assertNotNull($attachment->thumbnail_path);
        $this->assertNotNull($attachment->image_width);
        $this->assertNotNull($attachment->image_height);
    }
}
