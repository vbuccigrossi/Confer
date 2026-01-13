<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class WorkspaceWebController extends Controller
{
    /**
     * Show workspace creation form
     */
    public function create(): Response
    {
        return Inertia::render('Workspaces/Create');
    }

    /**
     * Store a new workspace
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:workspaces,slug',
        ]);

        $workspace = Workspace::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? \Illuminate\Support\Str::slug($validated['name']),
            'owner_id' => $request->user()->id,
            'settings' => [],
        ]);

        // Add owner as member
        $workspace->members()->create([
            'user_id' => $request->user()->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        // Create default #general channel
        $general = Conversation::create([
            'workspace_id' => $workspace->id,
            'type' => 'public_channel',
            'name' => 'general',
            'slug' => 'general',
            'topic' => 'Company-wide announcements and work-based matters',
            'description' => 'This is the one channel that will always include everyone. It\'s a great spot for announcements and team-wide conversations.',
            'created_by' => $request->user()->id,
        ]);

        // Add creator to general channel
        $general->members()->create([
            'user_id' => $request->user()->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        // Set as current workspace
        session(['current_workspace_id' => $workspace->id]);

        // Force session save
        session()->save();

        return redirect()->route('web.conversations.index')->with('success', 'Workspace created successfully');
    }

    /**
     * Switch to a different workspace
     */
    public function switch(Request $request, Workspace $workspace): RedirectResponse
    {
        // Verify user is a member
        if (!$request->user()->workspaces->contains($workspace)) {
            abort(403, 'You are not a member of this workspace');
        }

        session(['current_workspace_id' => $workspace->id]);
        session()->save();

        return redirect()->route('web.conversations.index');
    }

    /**
     * Show workspace settings
     */
    public function settings(Request $request): Response
    {
        $workspaceId = session('current_workspace_id');

        if (!$workspaceId) {
            return redirect()->route('workspaces.create');
        }

        $workspace = Workspace::with(['owner', 'members.user', 'invites' => function ($query) {
            $query->where('accepted_at', null)
                  ->where('expires_at', '>', now())
                  ->with('inviter');
        }])->findOrFail($workspaceId);

        $this->authorize('view', $workspace);

        return Inertia::render('Workspaces/Settings', [
            'workspace' => $workspace,
        ]);
    }

    /**
     * Update workspace settings
     */
    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $workspace->update($validated);

        return back()->with('success', 'Workspace updated successfully');
    }
}
