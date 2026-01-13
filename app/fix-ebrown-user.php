<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

// Find ebrown user
$user = User::where('email', 'LIKE', '%ebrown%')->first();

if (!$user) {
    echo "User not found!\n";
    exit(1);
}

echo "Found user: {$user->name} ({$user->email})\n";

// Get Main Workspace
$workspace = Workspace::where('name', 'Main Workspace')->first();

if (!$workspace) {
    echo "Main Workspace not found!\n";
    exit(1);
}

echo "Found workspace: {$workspace->name} (ID: {$workspace->id})\n\n";

// Check if already a member
$existingMember = WorkspaceMember::where('workspace_id', $workspace->id)
    ->where('user_id', $user->id)
    ->first();

if ($existingMember) {
    echo "User is already a member (Role: {$existingMember->role})\n";

    // Update role to admin if not already
    if ($existingMember->role !== 'admin') {
        $existingMember->update(['role' => 'admin']);
        echo "✓ Updated role to admin\n";
    }
} else {
    echo "Adding user to workspace...\n";

    // Unguard to allow mass assignment
    WorkspaceMember::unguard();

    WorkspaceMember::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'role' => 'admin',
        'joined_at' => now(),
    ]);

    WorkspaceMember::reguard();

    echo "✓ Added user to workspace as admin\n";
}

// Set as current workspace
if ($user->current_workspace_id !== $workspace->id) {
    $user->update(['current_workspace_id' => $workspace->id]);
    echo "✓ Set as current workspace\n";
} else {
    echo "✓ Already set as current workspace\n";
}

echo "\n✅ User {$user->name} is now properly configured and can access Main Workspace!\n";
