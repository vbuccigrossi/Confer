<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Mention;
use App\Models\User;
use App\Http\Requests\CreateMessageRequest;
use App\Http\Requests\UpdateMessageRequest;
use App\Events\MessageCreated;
use App\Events\MessageUpdated;
use App\Events\MessageDeleted;
use App\Services\NotificationService;
use App\Services\MarkdownService;
use App\Services\LinkPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    protected NotificationService $notificationService;
    protected MarkdownService $markdownService;
    protected LinkPreviewService $linkPreviewService;

    public function __construct(
        NotificationService $notificationService,
        MarkdownService $markdownService,
        LinkPreviewService $linkPreviewService
    ) {
        $this->notificationService = $notificationService;
        $this->markdownService = $markdownService;
        $this->linkPreviewService = $linkPreviewService;
    }
    /**
     * Display a paginated listing of messages in a conversation.
     */
    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('viewAny', [Message::class, $conversation]);

        $query = $conversation->messages()
            ->with(['user', 'reactions.user', 'linkPreviews', 'lastReplyUser'])
            ->rootMessages() // Only root messages, not thread replies
            ->latest()->orderBy('id', 'desc');

        // Cursor-based pagination
        $limit = min($request->input('limit', 50), 10000);

        if ($request->has('before')) {
            $query->where('id', '<', $request->input('before'));
        }

        if ($request->has('after')) {
            $query->where('id', '>', $request->input('after'));
        }

        $messages = $query->limit($limit)->get();

        // Load reply counts
        $messages->each(function ($message) {
            $message->reply_count = $message->replyCount();
        });

        return response()->json([
            'messages' => $messages,
            'has_more' => $messages->count() === $limit,
        ]);
    }

    /**
     * Store a newly created message.
     */
    public function store(CreateMessageRequest $request, Conversation $conversation): JsonResponse
    {
        // Check if this is a slash command
        if (!empty($request->body_md)) {
            $slashCommandService = app(\App\Services\SlashCommandService::class);
            if ($slashCommandService->isSlashCommand($request->body_md)) {
                $parsed = $slashCommandService->parseCommand($request->body_md);

                $result = $slashCommandService->executeCommand(
                    $parsed['command'],
                    $parsed['args'],
                    $conversation->workspace_id,
                    $conversation->id,
                    $request->user()->id
                );

                // Only create a message if there's something to show
                // - For errors, show the error message
                // - For native commands with a message, show it
                // - For bot commands that succeeded, the bot posts its own message
                if (!$result['success']) {
                    $responseMessage = "❌ Error: " . ($result['error'] ?? 'Command failed');

                    $message = Message::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $request->user()->id,
                        'body_md' => $responseMessage,
                    ]);

                    $message->load(['user', 'reactions.user', 'conversation']);
                    broadcast(new MessageCreated($message))->toOthers();

                    return response()->json($message, 201);
                }

                // For successful commands with a message (native commands like /help)
                if (!empty($result['message'])) {
                    $message = Message::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $request->user()->id,
                        'body_md' => $result['message'],
                    ]);

                    $message->load(['user', 'reactions.user', 'conversation']);
                    broadcast(new MessageCreated($message))->toOthers();

                    return response()->json($message, 201);
                }

                // Bot command succeeded - bot will post its own message
                return response()->json(['success' => true], 200);
            }
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()->id,
            'parent_message_id' => $request->parent_message_id,
            'body_md' => $request->body_md,
        ]);

        $message->load(['user', 'reactions.user', 'conversation']);

        // Create notifications and mention records for mentions
        $mentions = $this->markdownService->extractMentions($message->body_md);
        if (!empty($mentions['users'])) {
            foreach ($mentions['users'] as $username) {
                $mentionedUser = User::where('name', $username)->first();
                if ($mentionedUser) {
                    // Create mention record
                    Mention::create([
                        'message_id' => $message->id,
                        'mentioned_user_id' => $mentionedUser->id,
                        'mentioned_by_user_id' => $request->user()->id,
                        'is_read' => false,
                    ]);

                    // Create notification
                    $this->notificationService->createMentionNotification($message, $mentionedUser);
                }
            }
        }

        // Create notification for thread reply
        if ($message->parent_message_id) {
            $parentMessage = Message::find($message->parent_message_id);
            if ($parentMessage) {
                $this->notificationService->createThreadReplyNotification($message, $parentMessage);
            }
        }

        // Generate link previews for any URLs in the message
        $urls = $this->linkPreviewService->extractUrls($message->body_md);
        foreach ($urls as $url) {
            $this->linkPreviewService->generatePreview($message, $url);
        }

        // Reload message with link previews
        $message->load(['linkPreviews']);

        // Broadcast event
        broadcast(new MessageCreated($message))->toOthers();

        // Send push notifications to conversation members
        $this->notificationService->sendNewMessagePushNotifications($message);

        return response()->json($message, 201);
    }

    /**
     * Update the specified message.
     */
    public function update(UpdateMessageRequest $request, Message $message): JsonResponse
    {
        $message->update([
            'body_md' => $request->body_md,
            'edited_at' => now(),
        ]);

        $message->load(['user', 'reactions.user']);

        // Broadcast event
        broadcast(new MessageUpdated($message))->toOthers();

        return response()->json($message);
    }

    /**
     * Remove the specified message (soft delete).
     */
    public function destroy(Message $message): JsonResponse
    {
        Gate::authorize('delete', $message);

        $messageId = $message->id;
        $conversationId = $message->conversation_id;

        $message->delete();

        // Broadcast event
        broadcast(new MessageDeleted($messageId, $conversationId))->toOthers();

        return response()->json([
            'message' => 'Message deleted successfully',
        ]);
    }

    /**
     * Get replies (thread) for a message.
     */
    public function replies(Request $request, Message $message): JsonResponse
    {
        Gate::authorize('view', $message);

        $replies = Message::where('parent_message_id', $message->id)
            ->with(['user', 'reactions.user', 'linkPreviews'])
            ->oldest()
            ->get();

        return response()->json([
            'replies' => $replies,
        ]);
    }

    /**
     * Mark a message as read by updating last_read_message_id.
     */
    public function markAsRead(Request $request, Message $message): JsonResponse
    {
        Gate::authorize('markAsRead', $message);

        $membership = $message->conversation->members()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($membership) {
            $membership->update([
                'last_read_message_id' => $message->id,
            ]);
        }

        return response()->json([
            'message' => 'Message marked as read',
        ]);
    }
}
