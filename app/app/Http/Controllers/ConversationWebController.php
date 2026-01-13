<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\WorkspaceMember;
use App\Models\Message;
use App\Models\Mention;
use App\Models\User;
use App\Services\MarkdownService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ConversationWebController extends Controller
{
    /**
     * Show conversations index (main chat interface)
     */
    public function index(Request $request): Response
    {
        $workspaceId = $this->getWorkspaceId($request);

        $conversations = [];
        if ($workspaceId) {
            // Get all conversations user is member of
            $conversations = $this->getUserConversations($request, $workspaceId);
        }

        // Get available bots for this workspace
        $availableBots = [];
        if ($workspaceId) {
            $availableBots = $this->getAvailableBots($workspaceId);
        }

        return Inertia::render('Conversations/Index', [
            'conversations' => $conversations,
            'conversation' => null,
            'messages' => [],
            'availableBots' => $availableBots,
        ]);
    }

    /**
     * Show a specific conversation
     */
    public function show(Request $request, Conversation $conversation): Response
    {
        $workspaceId = $this->getWorkspaceId($request);

        // Get all conversations for sidebar
        $conversations = $workspaceId ? $this->getUserConversations($request, $workspaceId) : [];

        // Load current conversation
        $conversation->load(['members.user', 'creator']);
        if ($conversation->type === 'self') {
            $conversation->display_name = 'Notes to Self';
        } elseif (in_array($conversation->type, ['dm', 'group_dm', 'bot_dm'])) {
            $otherMembers = $conversation->members
                ->where('user_id', '!=', $request->user()->id)
                ->pluck('user.name')
                ->toArray();
            $conversation->display_name = implode(', ', $otherMembers);
        }

        // Load messages with reactions, user, attachments, and link previews
        $messages = Message::where('conversation_id', $conversation->id)
            ->whereNull('parent_message_id') // Only root messages, not thread replies
            ->with([
                'user',
                'reactions.user',
                'linkPreviews',
                'attachments' => function ($query) {
                    $query->select('id', 'message_id', 'file_name', 'mime_type', 'size_bytes', 'image_width', 'image_height', 'thumbnail_path', 'storage_path', 'disk', 'created_at')
                          ->orderBy('created_at');
                }
            ])
            ->withCount(['replies as thread_reply_count'])
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get()
            ->map(function ($message) {
                // Add signed URLs to attachments
                if ($message->attachments) {
                    $message->attachments->each(function ($attachment) {
                        $attachment->url = $attachment->getSignedUrl();
                        $attachment->thumbnail_url = $attachment->getThumbnailUrl();
                        $attachment->size_human = $attachment->size_human;
                    });
                }
                return $message;
            });

        // Mark conversation as read (update last_read_message_id to highest message ID including thread replies)
        $latestMessageId = Message::where('conversation_id', $conversation->id)
            ->max('id');

        if ($latestMessageId) {
            $conversation->members()
                ->where('user_id', $request->user()->id)
                ->update([
                    'last_read_message_id' => $latestMessageId,
                    'last_read_at' => now(),
                ]);
        }

        // Get available bots for this workspace
        $availableBots = $workspaceId ? $this->getAvailableBots($workspaceId) : [];

        return Inertia::render('Conversations/Index', [
            'conversations' => $conversations,
            'conversation' => $conversation,
            'messages' => $messages,
            'availableBots' => $availableBots,
        ]);
    }

    /**
     * Show create channel form
     */
    public function create(): Response
    {
        return Inertia::render('Conversations/Create');
    }

    /**
     * Store a new channel
     */
    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $this->getWorkspaceId($request);

        if (!$workspaceId) {
            return redirect()->route('workspaces.create');
        }

        $validated = $request->validate([
            'type' => 'required|in:public_channel,private_channel',
            'name' => 'required|string|max:80|regex:/^[a-z0-9-]+$/',
            'topic' => 'nullable|string|max:250',
            'description' => 'nullable|string',
        ]);

        // Check if channel name already exists in this workspace
        $existingChannel = Conversation::where('workspace_id', $workspaceId)
            ->where('slug', $validated['name'])
            ->first();

        if ($existingChannel) {
            return back()->withErrors([
                'name' => 'A channel with this name already exists in this workspace. Please choose a different name.'
            ])->withInput();
        }

        $conversation = Conversation::create([
            'workspace_id' => $workspaceId,
            'type' => $validated['type'],
            'name' => $validated['name'],
            'slug' => $validated['name'],
            'topic' => $validated['topic'] ?? null,
            'description' => $validated['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        // Unguard to allow conversation_id and user_id assignment
        ConversationMember::unguard();

        // Add creator as owner
        $conversation->members()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        // For public channels, automatically add all workspace members (excluding bots)
        if ($validated['type'] === 'public_channel') {
            $workspaceMembers = WorkspaceMember::where('workspace_id', $workspaceId)
                ->where('user_id', '!=', $request->user()->id) // Skip creator (already added)
                ->with('user')
                ->get();

            foreach ($workspaceMembers as $member) {
                // Skip bots - they must be added manually via /addbot
                if ($member->user && !str_ends_with($member->user->email, '@bots.local')) {
                    $conversation->members()->create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $member->user_id,
                        'role' => 'member',
                        'joined_at' => now(),
                    ]);
                }
            }
        }

        ConversationMember::reguard();

        return redirect()->route('web.conversations.show', $conversation->id)
            ->with('success', 'Channel created successfully');
    }

    /**
     * Show start DM form
     */
    public function startDm(Request $request): Response
    {
        $workspaceId = $this->getWorkspaceId($request);

        $workspaceMembers = WorkspaceMember::where('workspace_id', $workspaceId)
            ->with('user')
            ->get();

        return Inertia::render('Conversations/StartDm', [
            'workspaceMembers' => $workspaceMembers,
        ]);
    }

    /**
     * Create a DM or group DM
     */
    public function storeDm(Request $request): RedirectResponse
    {
        $workspaceId = $this->getWorkspaceId($request);

        if (!$workspaceId) {
            return redirect()->route('workspaces.create');
        }

        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $type = count($validated['user_ids']) === 1 ? 'dm' : 'group_dm';

        // Check if DM already exists
        if ($type === 'dm') {
            $existingDm = Conversation::where('workspace_id', $workspaceId)
                ->where('type', 'dm')
                ->whereHas('members', function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id);
                })
                ->whereHas('members', function ($query) use ($validated) {
                    $query->where('user_id', $validated['user_ids'][0]);
                })
                ->first();

            if ($existingDm) {
                return redirect()->route('web.conversations.show', $existingDm->id);
            }
        }

        // Create conversation
        $conversation = Conversation::create([
            'workspace_id' => $workspaceId,
            'type' => $type,
            'created_by' => $request->user()->id,
        ]);

        // Unguard to allow user_id and conversation_id assignment
        ConversationMember::unguard();

        // Add current user
        $conversation->members()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        // Add other users
        foreach ($validated['user_ids'] as $userId) {
            $conversation->members()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'role' => 'member',
                'joined_at' => now(),
            ]);
        }

        ConversationMember::reguard();

        return redirect()->route('web.conversations.show', $conversation->id);
    }

    /**
     * Show start bot DM form
     */
    public function startBotDm(Request $request): Response
    {
        $workspaceId = $this->getWorkspaceId($request);

        // Get available bots for this workspace
        $availableBots = \App\Models\BotInstallation::where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->with('bot')
            ->get()
            ->map(function ($installation) {
                return [
                    'id' => $installation->bot_id,
                    'name' => $installation->bot->name,
                    'description' => $installation->bot->description,
                ];
            });

        return Inertia::render('Conversations/StartBotDm', [
            'availableBots' => $availableBots,
        ]);
    }

    /**
     * Get or create the user's "Notes to Self" conversation
     */
    public function self(Request $request): RedirectResponse
    {
        $workspaceId = $this->getWorkspaceId($request);

        if (!$workspaceId) {
            return redirect()->route('workspaces.create');
        }

        // Check if user already has a self conversation in this workspace
        $conversation = Conversation::where('workspace_id', $workspaceId)
            ->where('type', Conversation::TYPE_SELF)
            ->where('created_by', $request->user()->id)
            ->first();

        if (!$conversation) {
            // Create a new self conversation
            $conversation = Conversation::create([
                'workspace_id' => $workspaceId,
                'type' => Conversation::TYPE_SELF,
                'name' => null,
                'created_by' => $request->user()->id,
            ]);

            // Unguard to allow conversation_id and user_id assignment
            ConversationMember::unguard();

            // Add user as the only member
            $conversation->members()->create([
                'conversation_id' => $conversation->id,
                'user_id' => $request->user()->id,
                'role' => 'owner',
                'joined_at' => now(),
            ]);

            ConversationMember::reguard();
        }

        return redirect()->route('web.conversations.show', $conversation->id);
    }

    /**
     * Create a Bot DM
     */
    public function storeBotDm(Request $request): RedirectResponse
    {
        $workspaceId = $this->getWorkspaceId($request);

        if (!$workspaceId) {
            return redirect()->route('workspaces.create');
        }

        $validated = $request->validate([
            'bot_id' => 'required|exists:bots,id',
        ]);

        // Check if bot DM already exists
        $bot = \App\Models\Bot::find($validated['bot_id']);
        $botUser = \App\Models\User::where('email', "bot_{$bot->id}@bots.local")->first();

        if ($botUser) {
            $existingBotDm = Conversation::where('workspace_id', $workspaceId)
                ->where('type', 'bot_dm')
                ->whereHas('members', function ($query) use ($request) {
                    $query->where('user_id', $request->user()->id);
                })
                ->whereHas('members', function ($query) use ($botUser) {
                    $query->where('user_id', $botUser->id);
                })
                ->first();

            if ($existingBotDm) {
                return redirect()->route('web.conversations.show', $existingBotDm->id);
            }
        }

        // Create bot user if doesn't exist
        if (!$botUser) {
            $botUser = \App\Models\User::create([
                'name' => $bot->name,
                'email' => "bot_{$bot->id}@bots.local",
                'password' => bcrypt(\Illuminate\Support\Str::random(32)),
            ]);
        }

        // Create conversation
        $conversation = Conversation::create([
            'workspace_id' => $workspaceId,
            'type' => 'bot_dm',
            'created_by' => $request->user()->id,
        ]);

        // Unguard to allow user_id and conversation_id assignment
        ConversationMember::unguard();

        // Add current user
        $conversation->members()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        // Add bot user
        $conversation->members()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $botUser->id,
            'role' => 'member',
            'joined_at' => now(),
        ]);

        ConversationMember::reguard();

        return redirect()->route('web.conversations.show', $conversation->id);
    }

    /**
     * Helper to get user conversations
     */
    private function getUserConversations(Request $request, int $workspaceId)
    {
        return Conversation::where('workspace_id', $workspaceId)
            ->whereHas('members', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->with(['members.user', 'creator'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($conv) use ($request) {
                if ($conv->type === 'self') {
                    $conv->display_name = 'Notes to Self';
                } elseif (in_array($conv->type, ['dm', 'group_dm', 'bot_dm'])) {
                    $otherMembers = $conv->members
                        ->where('user_id', '!=', $request->user()->id)
                        ->pluck('user.name')
                        ->toArray();
                    $conv->display_name = implode(', ', $otherMembers);
                }

                // Calculate unread count (exclude messages sent by current user)
                $member = $conv->members->where('user_id', $request->user()->id)->first();
                if ($member && $member->last_read_message_id) {
                    $conv->unread_count = Message::where('conversation_id', $conv->id)
                        ->where('id', '>', $member->last_read_message_id)
                        ->where('user_id', '!=', $request->user()->id)
                        ->count();
                } else {
                    // If never read, count all messages not sent by current user
                    $conv->unread_count = Message::where('conversation_id', $conv->id)
                        ->where('user_id', '!=', $request->user()->id)
                        ->count();
                }

                return $conv;
            });
    }

    /**
     * Update a conversation (rename)
     */
    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:80|regex:/^[a-z0-9-]+$/',
        ]);

        $conversation->update([
            'name' => $validated['name'],
            'slug' => $validated['name'],
        ]);

        return back();
    }

    /**
     * Delete a conversation
     */
    public function destroy(Conversation $conversation): RedirectResponse
    {
        $conversation->delete();

        return redirect()->route('web.conversations.index');
    }

    /**
     * Store a new message (web route)
     */
    public function storeMessage(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'body_md' => 'nullable|string|max:4000',
            'attachment_ids' => 'nullable|array',
            'attachment_ids.*' => 'exists:attachments,id',
        ]);

        // Require either body or attachments
        if (empty($validated['body_md']) && empty($validated['attachment_ids'])) {
            return back()->withErrors(['body_md' => 'Message must have text or attachments']);
        }

        // Check if this is a slash command
        if (!empty($validated['body_md'])) {
            $slashCommandService = app(\App\Services\SlashCommandService::class);
            if ($slashCommandService->isSlashCommand($validated['body_md'])) {
                $parsed = $slashCommandService->parseCommand($validated['body_md']);
                $workspaceId = $this->getWorkspaceId($request);

                $result = $slashCommandService->executeCommand(
                    $parsed['command'],
                    $parsed['args'],
                    $workspaceId,
                    $conversation->id,
                    $request->user()->id
                );

                // Only create a message if there's something to show
                // - For errors, show the error message
                // - For native commands with a message, show it
                // - For bot commands that succeeded, the bot posts its own message
                if (!$result['success']) {
                    $responseMessage = "❌ Error: " . ($result['error'] ?? 'Command failed');

                    Message::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $request->user()->id,
                        'body_md' => $responseMessage,
                        'body_html' => \Illuminate\Support\Str::markdown($responseMessage),
                    ]);
                } elseif (!empty($result['message'])) {
                    // Native commands like /help return a message
                    Message::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $request->user()->id,
                        'body_md' => $result['message'],
                        'body_html' => \Illuminate\Support\Str::markdown($result['message']),
                    ]);
                }
                // For successful bot commands, the bot posts its own message - no need to add another

                return back();
            }
        }

        $bodyMd = $validated['body_md'] ?? '';

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'body_md' => $bodyMd,
            'body_html' => !empty($bodyMd) ? \Illuminate\Support\Str::markdown($bodyMd) : '',
        ]);

        // Attach files to the message if provided
        if (!empty($validated['attachment_ids'])) {
            \App\Models\Attachment::whereIn('id', $validated['attachment_ids'])
                ->update(['message_id' => $message->id]);
        }

        // Create mention records
        $markdownService = app(MarkdownService::class);
        $mentions = $markdownService->extractMentions($message->body_md);
        if (!empty($mentions['users'])) {
            foreach ($mentions['users'] as $username) {
                $mentionedUser = User::where('name', $username)->first();
                if ($mentionedUser) {
                    Mention::create([
                        'message_id' => $message->id,
                        'mentioned_user_id' => $mentionedUser->id,
                        'mentioned_by_user_id' => $request->user()->id,
                        'is_read' => false,
                    ]);
                }
            }
        }

        // Generate link previews for any URLs in the message
        $linkPreviewService = app(\App\Services\LinkPreviewService::class);
        $urls = $linkPreviewService->extractUrls($message->body_md);
        foreach ($urls as $url) {
            $linkPreviewService->generatePreview($message, $url);
        }

        // Use back() to return to the previous page with fresh data
        return back();
    }

    /**
     * Show add members form
     */
    public function addMembers(Request $request, Conversation $conversation): Response
    {
        $workspaceId = $this->getWorkspaceId($request);

        // Get all workspace members who are NOT already in this conversation
        $existingMemberIds = $conversation->members()->pluck('user_id')->toArray();

        $availableMembers = WorkspaceMember::where('workspace_id', $workspaceId)
            ->whereNotIn('user_id', $existingMemberIds)
            ->with('user')
            ->get()
            ->map(function ($member) {
                return [
                    'id' => $member->user_id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                ];
            });

        return Inertia::render('Conversations/AddMembers', [
            'conversation' => $conversation,
            'availableMembers' => $availableMembers,
        ]);
    }

    /**
     * Add members to a channel
     */
    public function storeMembers(Request $request, Conversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        // Unguard to allow conversation_id and user_id assignment
        ConversationMember::unguard();

        foreach ($validated['user_ids'] as $userId) {
            // Check if user is not already a member
            if (!$conversation->members()->where('user_id', $userId)->exists()) {
                $conversation->members()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'role' => 'member',
                    'joined_at' => now(),
                ]);
            }
        }

        ConversationMember::reguard();

        return redirect()->route('web.conversations.show', $conversation->id)
            ->with('success', 'Members added successfully');
    }

    /**
     * Get available bots for a workspace
     */
    private function getAvailableBots(int $workspaceId): array
    {
        $availableBots = \App\Models\BotInstallation::where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->with('bot')
            ->get()
            ->map(function ($installation) {
                return [
                    'id' => $installation->bot_id,
                    'name' => $installation->bot->name,
                    'description' => $installation->bot->description,
                ];
            })
            ->toArray();

        return $availableBots;
    }

    /**
     * Get current workspace ID from session or user's first workspace
     */
    private function getWorkspaceId(Request $request): ?int
    {
        $workspaceId = session('current_workspace_id');

        if (!$workspaceId) {
            // Get user's first workspace membership
            $workspaceMember = \App\Models\WorkspaceMember::where('user_id', $request->user()->id)->first();
            if ($workspaceMember) {
                $workspaceId = $workspaceMember->workspace_id;
                session(['current_workspace_id' => $workspaceId]);
            }
        }

        return $workspaceId;
    }
}
