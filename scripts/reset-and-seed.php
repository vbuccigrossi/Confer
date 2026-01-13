<?php

/**
 * Reset database and create fresh admin with #general and #random channels
 */

echo "\n";
echo "========================================\n";
echo "Resetting Database to Fresh State\n";
echo "========================================\n\n";

// Step 1: Delete all data
echo "Step 1: Clearing all existing data...\n";

\App\Models\Message::query()->delete();
echo "  ✓ Deleted messages\n";

\App\Models\Reaction::query()->delete();
echo "  ✓ Deleted reactions\n";

\App\Models\Attachment::query()->delete();
echo "  ✓ Deleted attachments\n";

\App\Models\ConversationMember::query()->delete();
echo "  ✓ Deleted conversation members\n";

\App\Models\Conversation::query()->delete();
echo "  ✓ Deleted conversations\n";

\App\Models\WorkspaceMember::query()->delete();
echo "  ✓ Deleted workspace members\n";

\App\Models\Workspace::query()->delete();
echo "  ✓ Deleted workspaces\n";

\App\Models\Invite::query()->delete();
echo "  ✓ Deleted invites\n";

\App\Models\App::query()->delete();
echo "  ✓ Deleted apps\n";

\App\Models\User::query()->delete();
echo "  ✓ Deleted users\n";

\DB::statement('ALTER SEQUENCE users_id_seq RESTART WITH 1');
\DB::statement('ALTER SEQUENCE workspaces_id_seq RESTART WITH 1');
\DB::statement('ALTER SEQUENCE conversations_id_seq RESTART WITH 1');
\DB::statement('ALTER SEQUENCE messages_id_seq RESTART WITH 1');
echo "  ✓ Reset ID sequences\n";

echo "\n";

// Step 2: Get admin details from environment or prompt
$adminName = env('ADMIN_NAME', 'Admin');
$adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
$adminPassword = env('ADMIN_PASSWORD', 'password');

echo "Step 2: Creating admin user...\n";
$user = \App\Models\User::create([
    'name' => $adminName,
    'email' => $adminEmail,
    'password' => \Hash::make($adminPassword),
    'email_verified_at' => now(),
]);
echo "  ✓ Created admin: {$adminEmail}\n";

// Step 3: Create workspace
echo "\nStep 3: Creating default workspace...\n";
$workspace = \App\Models\Workspace::create([
    'name' => 'Main Workspace',
    'slug' => 'main-workspace',
    'owner_id' => $user->id,
]);
echo "  ✓ Created workspace: Main Workspace\n";

// Add user as admin member
\App\Models\WorkspaceMember::create([
    'workspace_id' => $workspace->id,
    'user_id' => $user->id,
    'role' => 'admin',
    'joined_at' => now(),
]);
echo "  ✓ Added admin to workspace\n";

// Set as current workspace
$user->update(['current_workspace_id' => $workspace->id]);
echo "  ✓ Set as current workspace\n";

// Step 4: Create #general channel
echo "\nStep 4: Creating #general channel...\n";
$general = \App\Models\Conversation::create([
    'workspace_id' => $workspace->id,
    'name' => '#general',
    'type' => 'channel',
    'is_private' => false,
    'description' => 'General discussions and announcements',
    'created_by' => $user->id,
]);
echo "  ✓ Created #general channel\n";

\App\Models\ConversationMember::create([
    'conversation_id' => $general->id,
    'user_id' => $user->id,
    'role' => 'member',
    'joined_at' => now(),
]);
echo "  ✓ Added admin to #general\n";

// Step 5: Create #random channel
echo "\nStep 5: Creating #random channel...\n";
$random = \App\Models\Conversation::create([
    'workspace_id' => $workspace->id,
    'name' => '#random',
    'type' => 'channel',
    'is_private' => false,
    'description' => 'Random chatter and off-topic discussions',
    'created_by' => $user->id,
]);
echo "  ✓ Created #random channel\n";

\App\Models\ConversationMember::create([
    'conversation_id' => $random->id,
    'user_id' => $user->id,
    'role' => 'member',
    'joined_at' => now(),
]);
echo "  ✓ Added admin to #random\n";

// Step 6: Summary
echo "\n";
echo "========================================\n";
echo "Database Reset Complete!\n";
echo "========================================\n\n";

echo "Admin User:\n";
echo "  Email: {$adminEmail}\n";
echo "  Password: (as provided)\n\n";

echo "Workspace:\n";
echo "  Name: Main Workspace\n";
echo "  Slug: main-workspace\n\n";

echo "Channels:\n";
echo "  #general - General discussions and announcements\n";
echo "  #random - Random chatter and off-topic discussions\n\n";

echo "✓ Ready for production!\n\n";
