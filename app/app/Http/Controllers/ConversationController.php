<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateConversationRequest;
use App\Http\Requests\UpdateConversationRequest;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        $conversations = $currentUser
            ->conversations()
            ->with(['workspace', 'creator', 'members.user'])
            ->where('workspace_id', $request->workspace_id)
            ->active()
            ->orderBy('created_at', 'desc')
            ->get();

        // Add display_name for DMs, bot DMs, and self conversations, calculate unread counts, and get last message
        foreach ($conversations as $conversation) {
            if ($conversation->type === 'self') {
                $conversation->display_name = 'Notes to Self';
            } elseif (in_array($conversation->type, ['dm', 'group_dm', 'bot_dm'])) {
                $otherMembers = $conversation->members
                    ->where('user_id', '!=', $currentUser->id)
                    ->pluck('user.name')
                    ->toArray();
                $conversation->display_name = implode(', ', $otherMembers);
            }

            // Get last message for preview
            $lastMessage = DB::table('messages')
                ->where('conversation_id', $conversation->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($lastMessage) {
                $conversation->last_message_at = $lastMessage->created_at;
                $conversation->last_message = [
                    'id' => $lastMessage->id,
                    'body_md' => $lastMessage->body_md,
                    'user_id' => $lastMessage->user_id,
                    'created_at' => $lastMessage->created_at,
                ];

                // Get user info for group DMs
                if ($conversation->type === 'group_dm') {
                    $msgUser = DB::table('users')->where('id', $lastMessage->user_id)->first();
                    if ($msgUser) {
                        $conversation->last_message['user'] = [
                            'id' => $msgUser->id,
                            'name' => $msgUser->name,
                        ];
                    }
                }
            } else {
                $conversation->last_message_at = null;
                $conversation->last_message = null;
            }

            // Calculate unread count (excluding user's own messages)
            $member = $conversation->members->firstWhere('user_id', $currentUser->id);
            if ($member && $member->last_read_message_id) {
                // Count messages newer than the last read message, excluding user's own messages
                $conversation->unread_count = DB::table('messages')
                    ->where('conversation_id', $conversation->id)
                    ->where('id', '>', $member->last_read_message_id)
                    ->where('user_id', '!=', $currentUser->id)
                    ->count();
            } else {
                // If never read, count all messages except user's own
                $conversation->unread_count = DB::table('messages')
                    ->where('conversation_id', $conversation->id)
                    ->where('user_id', '!=', $currentUser->id)
                    ->count();
            }
        }

        return response()->json($conversations);
    }

    public function store(CreateConversationRequest $request): JsonResponse
    {
        $type = $request->type;
        $currentUser = $request->user();

        // Handle DM creation - check for existing DM
        if ($type === Conversation::TYPE_DM) {
            $memberIds = $request->member_ids;
            $memberIds[] = $currentUser->id;
            sort($memberIds);

            $existing = Conversation::where('workspace_id', $request->workspace_id)
                ->where('type', Conversation::TYPE_DM)
                ->whereHas('users', function ($query) use ($memberIds) {
                    $query->whereIn('users.id', $memberIds);
                }, '=', count($memberIds))
                ->first();

            if ($existing) {
                return response()->json($existing->load(['workspace', 'creator', 'members.user']), 200);
            }
        }

        $conversation = DB::transaction(function () use ($request, $type, $currentUser) {
            $conversation = Conversation::create([
                'workspace_id' => $request->workspace_id,
                'type' => $type,
                'name' => $request->name,
                'slug' => $request->slug,
                'topic' => $request->topic,
                'description' => $request->description,
                'created_by' => $currentUser->id,
            ]);

            // Add creator as owner
            $conversation->addMember($currentUser, 'owner');

            // For public channels, automatically add all workspace members (excluding bots)
            if ($type === Conversation::TYPE_PUBLIC_CHANNEL) {
                $workspaceMembers = \App\Models\WorkspaceMember::where('workspace_id', $request->workspace_id)
                    ->where('user_id', '!=', $currentUser->id)
                    ->with('user')
                    ->get();

                foreach ($workspaceMembers as $workspaceMember) {
                    // Skip bots - they must be added manually via /addbot
                    if ($workspaceMember->user && !str_ends_with($workspaceMember->user->email, '@bots.local')) {
                        $conversation->addMember($workspaceMember->user, 'member');
                    }
                }
            } elseif ($request->has('member_ids')) {
                // For private channels/DMs, only add specified members
                foreach ($request->member_ids as $userId) {
                    if ($userId != $currentUser->id) {
                        $user = User::find($userId);
                        if ($user) {
                            $conversation->addMember($user, 'member');
                        }
                    }
                }
            }

            return $conversation;
        });

        return response()->json($conversation->load(['workspace', 'creator', 'members.user']), 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        return response()->json($conversation->load(['workspace', 'creator', 'members.user']));
    }

    public function update(UpdateConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $conversation->update($request->validated());

        return response()->json($conversation->load(['workspace', 'creator', 'members']));
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('delete', $conversation);

        $conversation->delete();

        return response()->json(['message' => 'Conversation deleted successfully']);
    }

    public function archive(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('archive', $conversation);

        $conversation->archive();

        return response()->json($conversation);
    }

    public function unarchive(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('archive', $conversation);

        $conversation->unarchive();

        return response()->json($conversation);
    }

    public function discover(Request $request): JsonResponse
    {
        $publicChannels = Conversation::where('workspace_id', $request->workspace_id)
            ->where('type', Conversation::TYPE_PUBLIC_CHANNEL)
            ->whereDoesntHave('members', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->active()
            ->with(['creator', 'members'])
            ->get();

        return response()->json($publicChannels);
    }

    /**
     * Get or create the user's "Notes to Self" conversation.
     * Each user has one self conversation per workspace.
     */
    public function self(Request $request): JsonResponse
    {
        $request->validate([
            'workspace_id' => 'required|integer|exists:workspaces,id',
        ]);

        $currentUser = $request->user();
        $workspaceId = $request->workspace_id;

        // Check if user already has a self conversation in this workspace
        $conversation = Conversation::where('workspace_id', $workspaceId)
            ->where('type', Conversation::TYPE_SELF)
            ->where('created_by', $currentUser->id)
            ->first();

        if (!$conversation) {
            // Create a new self conversation
            $conversation = DB::transaction(function () use ($currentUser, $workspaceId) {
                $conversation = Conversation::create([
                    'workspace_id' => $workspaceId,
                    'type' => Conversation::TYPE_SELF,
                    'name' => null,
                    'created_by' => $currentUser->id,
                ]);

                // Add user as the only member
                $conversation->addMember($currentUser, 'owner');

                return $conversation;
            });
        }

        $conversation->load(['workspace', 'creator', 'members.user']);
        $conversation->display_name = 'Notes to Self';

        return response()->json($conversation);
    }

    /**
     * Start/update typing indicator
     */
    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $typingService = app(\App\Services\TypingIndicatorService::class);
        $typingService->startTyping(
            $conversation->id,
            $request->user()->id,
            $request->user()->name
        );

        // Broadcast typing event via WebSocket
        broadcast(new \App\Events\UserTyping(
            $conversation->id,
            $request->user()->id,
            $request->user()->name
        ))->toOthers();

        return response()->json(['status' => 'typing']);
    }

    /**
     * Get currently typing users
     */
    public function getTyping(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $typingService = app(\App\Services\TypingIndicatorService::class);
        $typingUsers = $typingService->getTypingUsers(
            $conversation->id,
            $request->user()->id
        );

        return response()->json([
            'typing_users' => $typingUsers,
            'message' => $typingService->getTypingMessage($typingUsers),
        ]);
    }
}
