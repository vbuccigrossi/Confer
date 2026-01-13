<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkspaceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $workspaces = $request->user()
            ->workspaces()
            ->with(['owner', 'members.user'])
            ->get();

        return response()->json($workspaces);
    }

    public function store(CreateWorkspaceRequest $request): JsonResponse
    {
        $workspace = Workspace::create([
            'name' => $request->name,
            'slug' => $request->slug,
            'owner_id' => $request->user()->id,
            'settings' => $request->settings ?? [],
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $request->user()->id,
            'role' => 'owner',
        ]);

        return response()->json($workspace->load(['owner', 'members']), 201);
    }

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('view', $workspace);

        return response()->json($workspace->load(['owner', 'members.user']));
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): JsonResponse
    {
        $workspace->update($request->validated());

        return response()->json($workspace->load(['owner', 'members']));
    }

    public function destroy(Request $request, Workspace $workspace): JsonResponse
    {
        Gate::authorize('delete', $workspace);

        $workspace->delete();

        return response()->json(['message' => 'Workspace deleted successfully'], 200);
    }

    public function members(Request $request, Workspace $workspace): JsonResponse
    {
        // Verify user is a member of this workspace
        Gate::authorize('view', $workspace);

        $members = WorkspaceMember::where('workspace_id', $workspace->id)
            ->with('user')
            ->get();

        return response()->json($members);
    }
}
