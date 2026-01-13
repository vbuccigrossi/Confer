<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        // Use the Vite manifest file modification time as version
        $manifestPath = public_path('build/manifest.json');

        if (file_exists($manifestPath)) {
            return md5_file($manifestPath);
        }

        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? $request->user()->only([
                    'id',
                    'name',
                    'email',
                    'profile_photo_url',
                    'status',
                    'status_message',
                    'status_emoji',
                    'status_expires_at',
                    'is_dnd',
                    'dnd_until',
                    'is_online',
                ]) : null,
            ],
            'currentWorkspace' => $request->user() ? function () use ($request) {
                // Get user's current workspace or their first workspace membership
                $workspaceMember = \App\Models\WorkspaceMember::where('user_id', $request->user()->id)
                    ->with('workspace.members.user')
                    ->first();

                if ($workspaceMember) {
                    $workspace = $workspaceMember->workspace;
                    session(['current_workspace_id' => $workspace->id]);

                    // Auto-add user to #general and #random channels
                    $channels = \App\Models\Conversation::where('workspace_id', $workspace->id)
                        ->whereIn('slug', ['general', 'random'])
                        ->get();

                    foreach ($channels as $channel) {
                        if (!$channel->members()->where('user_id', $request->user()->id)->exists()) {
                            // Unguard to allow conversation_id and user_id assignment
                            \App\Models\ConversationMember::unguard();

                            $channel->members()->create([
                                'conversation_id' => $channel->id,
                                'user_id' => $request->user()->id,
                                'role' => 'member',
                                'joined_at' => now(),
                            ]);

                            \App\Models\ConversationMember::reguard();
                        }
                    }

                    return $workspace;
                }

                return null;
            } : null,
            'workspaces' => [], // Hide workspace switcher - we only have one workspace
        ];
    }
}
