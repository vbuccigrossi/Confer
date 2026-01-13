<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Feature__Search__BasicTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Workspace $workspace;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Alice']);
        $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
        $this->workspace->members()->create(['user_id' => $this->user->id, 'role' => 'owner']);

        $this->conversation = Conversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'name' => 'general',
            'type' => Conversation::TYPE_PUBLIC_CHANNEL,
            'created_by' => $this->user->id,
        ]);
        $this->conversation->members()->create(['user_id' => $this->user->id, 'role' => 'member']);
    }

    public function test_user_can_search_messages(): void
    {
        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'body_md' => 'Hello world, this is a test message',
        ]);

        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'body_md' => 'Another unrelated message',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=hello');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'results' => [
                    '*' => [
                        'id',
                        'conversation_id',
                        'user',
                        'body_md',
                        'snippet',
                        'created_at',
                    ],
                ],
                'next_cursor',
                'has_more',
            ])
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.body_md', 'Hello world, this is a test message');
    }

    public function test_search_requires_query(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/search');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_search_empty_query_returns_validation_error(): void
    {
        // URL encoded spaces count as empty after trim in validation
        $response = $this->actingAs($this->user)->getJson('/api/search?q=' . urlencode('   '));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_search_with_phrase(): void
    {
        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'body_md' => 'This is an exact phrase match',
        ]);

        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'body_md' => 'phrase exact match This',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q="exact phrase"');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'results');
    }

    public function test_search_with_negation(): void
    {
        $driver = \DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            $this->markTestSkipped('Negation only works with PostgreSQL FTS');
        }

        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'body_md' => 'I love Laravel',
        ]);

        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'body_md' => 'I love React',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=love -React');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.body_md', 'I love Laravel');
    }

    public function test_search_respects_conversation_membership(): void
    {
        $otherUser = User::factory()->create();
        $privateConversation = Conversation::factory()->create([
            'workspace_id' => $this->workspace->id,
            'type' => Conversation::TYPE_PRIVATE_CHANNEL,
            'created_by' => $otherUser->id,
        ]);
        $privateConversation->members()->create(['user_id' => $otherUser->id, 'role' => 'member']);

        Message::factory()->create([
            'conversation_id' => $privateConversation->id,
            'user_id' => $otherUser->id,
            'body_md' => 'Secret message in private channel',
        ]);

        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'body_md' => 'Public message with secret keyword',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=secret');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.body_md', 'Public message with secret keyword');
    }

    public function test_search_pagination(): void
    {
        // Create 25 messages
        for ($i = 1; $i <= 25; $i++) {
            Message::factory()->create([
                'conversation_id' => $this->conversation->id,
                'user_id' => $this->user->id,
                'body_md' => "Test message number $i",
            ]);
        }

        $response = $this->actingAs($this->user)->getJson('/api/search?q=test&limit=10');

        $response->assertStatus(200)
            ->assertJsonCount(10, 'results')
            ->assertJsonPath('has_more', true);

        // Get next page
        $cursor = $response->json('next_cursor');
        $this->assertNotNull($cursor);

        $response2 = $this->actingAs($this->user)->getJson('/api/search?q=test&limit=10&cursor=' . $cursor);

        $response2->assertStatus(200)
            ->assertJsonCount(10, 'results')
            ->assertJsonPath('has_more', true);
    }

    public function test_search_returns_snippet(): void
    {
        $driver = \DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            $this->markTestSkipped('ts_headline snippets only work with PostgreSQL FTS');
        }

        Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'user_id' => $this->user->id,
            'body_md' => 'This is a very long message that contains the search term somewhere in the middle of a lot of other text. The search term we are looking for is Laravel. This message has a lot of words to demonstrate snippet generation.',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=Laravel');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'results');

        $snippet = $response->json('results.0.snippet');
        $this->assertNotNull($snippet);
        $this->assertStringContainsString('Laravel', $snippet);
    }
}
