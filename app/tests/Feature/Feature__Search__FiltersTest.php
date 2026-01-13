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

class Feature__Search__FiltersTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected Workspace $workspace;
    protected Conversation $generalChannel;
    protected Conversation $randomChannel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Alice']);
        $this->otherUser = User::factory()->create(['name' => 'Bob']);
        
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
        $this->workspace->members()->create(['user_id' => $this->user->id, 'role' => 'owner']);
        $this->workspace->members()->create(['user_id' => $this->otherUser->id, 'role' => 'member']);

        $this->generalChannel = Conversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'general',
            'type' => Conversation::TYPE_PUBLIC_CHANNEL,
            'created_by' => $this->user->id,
        ]);
        $this->generalChannel->members()->create(['user_id' => $this->user->id, 'role' => 'member']);
        $this->generalChannel->members()->create(['user_id' => $this->otherUser->id, 'role' => 'member']);

        $this->randomChannel = Conversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'random',
            'type' => Conversation::TYPE_PUBLIC_CHANNEL,
            'created_by' => $this->user->id,
        ]);
        $this->randomChannel->members()->create(['user_id' => $this->user->id, 'role' => 'member']);
    }

    public function test_search_with_in_filter(): void
    {
        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Unicorn sighting in general channel',
        ]);

        Message::factory()->create([
            'conversation_id' => $this->randomChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Unicorn sighting in random channel',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=unicorn in:#general');

        $response->assertStatus(200);

        // At least one result should be from general channel
        $generalResults = collect($response->json('results'))
            ->where('conversation_name', 'general');

        $this->assertGreaterThan(0, $generalResults->count(),
            'Expected at least one result from #general channel');
    }

    public function test_search_with_from_filter(): void
    {
        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Dragon spotted by Alice',
        ]);

        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->otherUser->id,
            'body_md' => 'Dragon spotted by Bob',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=dragon from:@Bob');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.user.name', 'Bob');
    }

    public function test_search_with_has_file_filter(): void
    {
        Storage::fake('local');

        $messageWithFile = Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Phoenix photo attached',
        ]);

        Attachment::factory()->create([
            'message_id' => $messageWithFile->id,
            'uploader_id' => $this->user->id,
        ]);

        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Phoenix sighting no photo',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=phoenix has:file');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.body_md', 'Phoenix photo attached');
    }

    public function test_search_with_since_filter(): void
    {
        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Old message',
            'created_at' => '2024-01-01 10:00:00',
        ]);

        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Recent message',
            'created_at' => '2024-06-01 10:00:00',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=message since:2024-05-01');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.body_md', 'Recent message');
    }

    public function test_search_with_until_filter(): void
    {
        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Old message',
            'created_at' => '2024-01-01 10:00:00',
        ]);

        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Recent message',
            'created_at' => '2024-06-01 10:00:00',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=message until:2024-03-01');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.body_md', 'Old message');
    }

    public function test_search_with_date_range(): void
    {
        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Message 1',
            'created_at' => '2024-01-01 10:00:00',
        ]);

        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Message 2',
            'created_at' => '2024-03-15 10:00:00',
        ]);

        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Message 3',
            'created_at' => '2024-06-01 10:00:00',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=message since:2024-02-01 until:2024-05-01');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.body_md', 'Message 2');
    }

    public function test_search_with_multiple_filters(): void
    {
        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Wizard spell cast by Alice in general',
        ]);

        Message::factory()->create([
            'conversation_id' => $this->generalChannel->id,
            'user_id' => $this->otherUser->id,
            'body_md' => 'Wizard spell cast by Bob in general',
        ]);

        Message::factory()->create([
            'conversation_id' => $this->randomChannel->id,
            'user_id' => $this->user->id,
            'body_md' => 'Wizard spell cast by Alice in random',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=wizard in:#general from:@Alice');

        $response->assertStatus(200);

        // At least one result should be from general channel by Alice
        $matchingResults = collect($response->json('results'))
            ->where('conversation_name', 'general')
            ->where('user.name', 'Alice');

        $this->assertGreaterThan(0, $matchingResults->count(),
            'Expected at least one result from #general by Alice');
    }
}
