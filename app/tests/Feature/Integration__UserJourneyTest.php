<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive integration test simulating the complete user journey
 * from registration to messaging across multiple sessions.
 */
class Integration__UserJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create default owner user first
        $owner = User::factory()->create([
            'name' => 'System',
            'email' => 'system@latch.local',
        ]);

        // Create default workspace that should exist
        Workspace::factory()->create([
            'name' => 'Latch',
            'slug' => 'default',
            'owner_id' => $owner->id,
        ]);
    }

    /**
     * Test complete user journey: register -> login -> view dashboard
     */
    public function test_new_user_can_register_and_access_dashboard(): void
    {
        // Step 1: Register a new user
        $response = $this->post('/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => true,
        ]);

        $response->assertStatus(302);

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'email' => 'alice@example.com',
        ]);

        $user = User::where('email', 'alice@example.com')->first();
        $this->assertNotNull($user);

        // Step 2: User should be authenticated after registration
        $this->assertAuthenticated();

        // Step 3: Dashboard should redirect to conversations index
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertRedirect(route('web.conversations.index'));

        // Step 4: Verify user can access conversations index
        $response = $this->actingAs($user)->get(route('web.conversations.index'));
        $response->assertStatus(200);
    }

    /**
     * Test that users are auto-added to default workspace and #general channel
     */
    public function test_user_auto_joins_default_workspace_and_general_channel(): void
    {
        $workspace = Workspace::where('slug', 'default')->first();
        $owner = User::where('email', 'system@latch.local')->first();

        // Create #general channel
        $general = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'public_channel',
            'name' => 'general',
            'slug' => 'general',
            'created_by' => $owner->id,
        ]);

        // Create a user
        $user = User::factory()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);

        // Visit the conversations page (triggers middleware that auto-adds user)
        $response = $this->actingAs($user)->get(route('web.conversations.index'));
        $response->assertStatus(200);

        // Verify user is now a member of the default workspace
        $this->assertDatabaseHas('workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);

        // Verify user is now a member of #general
        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $general->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Test creating a channel and viewing it
     */
    public function test_user_can_create_and_view_channel(): void
    {
        $workspace = Workspace::where('slug', 'default')->first();
        $user = User::factory()->create();

        // Add user to workspace
        $workspace->members()->create([
            'user_id' => $user->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        // Create a channel
        $response = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('web.conversations.store'), [
                'type' => 'public_channel',
                'name' => 'random',
                'topic' => 'Random chat',
            ]);

        $response->assertStatus(302);

        // Verify channel was created
        $this->assertDatabaseHas('conversations', [
            'workspace_id' => $workspace->id,
            'name' => 'random',
            'slug' => 'random',
            'type' => 'public_channel',
        ]);

        $channel = Conversation::where('slug', 'random')->first();
        $this->assertNotNull($channel);

        // Verify user is a member of the channel
        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $channel->id,
            'user_id' => $user->id,
        ]);

        // Verify user can view the channel
        $response = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('web.conversations.show', $channel->id));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->component('Conversations/Index')
                ->has('conversation')
                ->where('conversation.id', $channel->id)
        );
    }

    /**
     * Test sending and viewing messages
     */
    public function test_user_can_send_and_view_messages(): void
    {
        $workspace = Workspace::where('slug', 'default')->first();
        $user = User::factory()->create(['name' => 'Charlie']);

        // Create a channel
        $channel = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'public_channel',
            'name' => 'test-channel',
            'slug' => 'test-channel',
            'created_by' => $user->id,
        ]);

        // Add user to channel
        $channel->members()->create([
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        // Send a message via API
        $response = $this->actingAs($user)->postJson("/api/conversations/{$channel->id}/messages", [
            'body_md' => 'Hello, world!',
        ]);

        $response->assertStatus(201);

        // Verify message was created
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $channel->id,
            'user_id' => $user->id,
            'body_md' => 'Hello, world!',
        ]);

        // View the channel and verify message appears
        $response = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('web.conversations.show', $channel->id));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('messages', 1)
                ->where('messages.0.body_md', 'Hello, world!')
        );
    }

    /**
     * Test message persistence across sessions (logout/login)
     */
    public function test_messages_persist_across_sessions(): void
    {
        $workspace = Workspace::where('slug', 'default')->first();
        $user = User::factory()->create([
            'email' => 'dave@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Create a channel
        $channel = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'public_channel',
            'name' => 'persistent',
            'slug' => 'persistent',
            'created_by' => $user->id,
        ]);

        $channel->members()->create([
            'user_id' => $user->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        // Send a message
        $this->actingAs($user)->postJson("/api/conversations/{$channel->id}/messages", [
            'body_md' => 'This should persist',
        ]);

        // Logout
        $this->post('/logout');
        $this->assertGuest();

        // Login again
        $response = $this->post('/login', [
            'email' => 'dave@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(302);
        $this->assertAuthenticated();

        // View the channel - message should still be there
        $response = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('web.conversations.show', $channel->id));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) =>
            $page->has('messages', 1)
                ->where('messages.0.body_md', 'This should persist')
        );
    }

    /**
     * Test channels persist across sessions
     */
    public function test_channels_persist_across_sessions(): void
    {
        $workspace = Workspace::where('slug', 'default')->first();
        $user = User::factory()->create([
            'email' => 'eve@example.com',
            'password' => bcrypt('password123'),
        ]);

        $workspace->members()->create([
            'user_id' => $user->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        // Create a channel
        $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->post(route('web.conversations.store'), [
                'type' => 'public_channel',
                'name' => 'my-channel',
                'topic' => 'My channel',
            ]);

        $channel = Conversation::where('slug', 'my-channel')->first();
        $this->assertNotNull($channel);

        // Logout
        $this->post('/logout');
        $this->assertGuest();

        // Login again
        $this->post('/login', [
            'email' => 'eve@example.com',
            'password' => 'password123',
        ]);

        // View conversations - channel should be in the list
        // Middleware will auto-add user back to workspace when they visit this page
        $response = $this->actingAs($user)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('web.conversations.index'));
        $response->assertStatus(200);

        // Channel should exist and user should still be a member
        $this->assertTrue($channel->members()->where('user_id', $user->id)->exists(),
            'User should still be a member of the channel after logout/login');
    }

    /**
     * Test multiple users can see messages in the same channel
     */
    public function test_multiple_users_can_see_messages_in_shared_channel(): void
    {
        $workspace = Workspace::where('slug', 'default')->first();

        $user1 = User::factory()->create(['name' => 'User One']);
        $user2 = User::factory()->create(['name' => 'User Two']);

        // Create a channel
        $channel = Conversation::factory()->create([
            'workspace_id' => $workspace->id,
            'type' => 'public_channel',
            'name' => 'shared',
            'slug' => 'shared',
            'created_by' => $user1->id,
        ]);

        // Add both users to channel
        $channel->members()->create(['user_id' => $user1->id, 'role' => 'owner', 'joined_at' => now()]);
        $channel->members()->create(['user_id' => $user2->id, 'role' => 'member', 'joined_at' => now()]);

        // User 1 sends a message
        $this->actingAs($user1)->postJson("/api/conversations/{$channel->id}/messages", [
            'body_md' => 'Message from User One',
        ]);

        // User 2 sends a message
        $this->actingAs($user2)->postJson("/api/conversations/{$channel->id}/messages", [
            'body_md' => 'Message from User Two',
        ]);

        // Both users should see both messages
        $response = $this->actingAs($user1)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('web.conversations.show', $channel->id));
        $response->assertInertia(fn ($page) => $page->has('messages', 2));

        $response = $this->actingAs($user2)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get(route('web.conversations.show', $channel->id));
        $response->assertInertia(fn ($page) => $page->has('messages', 2));
    }

    /**
     * Test session workspace persistence
     */
    public function test_workspace_persists_in_session(): void
    {
        $workspace = Workspace::where('slug', 'default')->first();
        $user = User::factory()->create();

        // Visit conversations page
        $response = $this->actingAs($user)->get(route('web.conversations.index'));
        $response->assertStatus(200);

        // Verify session has workspace_id
        $this->assertEquals($workspace->id, session('current_workspace_id'));

        // Make another request - workspace should still be in session
        $response = $this->actingAs($user)->get(route('web.conversations.index'));
        $response->assertStatus(200);
        $this->assertEquals($workspace->id, session('current_workspace_id'));
    }
}
