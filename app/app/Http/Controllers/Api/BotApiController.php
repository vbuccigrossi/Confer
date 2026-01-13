<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BotApiController extends Controller
{
    /**
     * Send a message as the bot to a conversation
     */
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'conversation_id' => 'required|exists:conversations,id',
            'text' => 'required|string|max:10000',
            'thread_id' => 'nullable|exists:messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $botInstallation = $request->get('bot_installation');
        $conversation = Conversation::find($request->conversation_id);

        // Verify conversation belongs to bot's workspace
        if ($conversation->workspace_id !== $botInstallation->workspace_id) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Bot does not have access to this conversation',
            ], 403);
        }

        // Create a user for the bot if it doesn't exist
        // For now, we'll use a special bot user pattern
        $botUser = \App\Models\User::firstOrCreate(
            ['email' => "bot_{$botInstallation->bot_id}@bots.local"],
            [
                'name' => $botInstallation->bot->name,
                'password' => bcrypt(\Illuminate\Support\Str::random(32)),
            ]
        );

        // Parse markdown
        $bodyHtml = \Illuminate\Support\Str::markdown($request->text);
        // Add target="_blank" to all links so they open in new tabs
        $bodyHtml = preg_replace("/<a\s+href=/i", "<a target=\"_blank\" rel=\"noopener noreferrer\" href=", $bodyHtml);


        // Create message
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $botUser->id,
            'body_md' => $request->text,
            'body_html' => $bodyHtml,
            'parent_message_id' => $request->thread_id,
        ]);

        // Load relationships
        $message->load(['user', 'reactions', 'attachments']);

        return response()->json([
            'success' => true,
            'message' => $message,
        ], 201);
    }

    /**
     * Get conversation details
     */
    public function getConversation(Request $request, $id)
    {
        $botInstallation = $request->get('bot_installation');
        $conversation = Conversation::find($id);

        if (!$conversation) {
            return response()->json([
                'error' => 'Not found',
                'message' => 'Conversation not found',
            ], 404);
        }

        // Verify conversation belongs to bot's workspace
        if ($conversation->workspace_id !== $botInstallation->workspace_id) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Bot does not have access to this conversation',
            ], 403);
        }

        return response()->json([
            'conversation' => $conversation->load(['members.user']),
        ]);
    }
}
