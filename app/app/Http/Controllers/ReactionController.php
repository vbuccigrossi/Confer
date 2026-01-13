<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Reaction;
use App\Http\Requests\AddReactionRequest;
use App\Events\ReactionAdded;
use App\Events\ReactionRemoved;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReactionController extends Controller
{
    /**
     * Add a reaction to a message.
     */
    public function store(AddReactionRequest $request, Message $message): JsonResponse
    {
        // Check if reaction already exists (prevent duplicates)
        $existing = Reaction::where([
            ['message_id', '=', $message->id],
            ['user_id', '=', $request->user()->id],
            ['emoji', '=', $request->emoji],
        ])->first();

        if ($existing) {
            return response()->json($existing->load('user'), 200);
        }

        // Create new reaction
        $reaction = Reaction::create([
            'message_id' => $message->id,
            'user_id' => $request->user()->id,
            'emoji' => $request->emoji,
        ]);

        $reaction->load('user');

        // Broadcast event
        broadcast(new ReactionAdded($reaction))->toOthers();

        return response()->json($reaction, 201);
    }

    /**
     * Remove a reaction from a message.
     */
    public function destroy(Request $request, Message $message, string $emoji): JsonResponse
    {
        $reaction = Reaction::where([
            ['message_id', '=', $message->id],
            ['user_id', '=', $request->user()->id],
            ['emoji', '=', $emoji],
        ])->first();

        if (!$reaction) {
            return response()->json([
                'message' => 'Reaction not found',
            ], 404);
        }

        $reactionId = $reaction->id;
        $messageId = $message->id;
        $conversationId = $message->conversation_id;

        $reaction->delete();

        // Broadcast event
        broadcast(new ReactionRemoved($reactionId, $messageId, $conversationId))->toOthers();

        return response()->json([
            'message' => 'Reaction removed successfully',
        ]);
    }

    /**
     * Remove a reaction by its ID.
     */
    public function destroyById(Request $request, Reaction $reaction): JsonResponse
    {
        // Ensure the user owns this reaction
        if ($reaction->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $reactionId = $reaction->id;
        $messageId = $reaction->message_id;
        $conversationId = $reaction->message->conversation_id;

        $reaction->delete();

        // Broadcast event
        broadcast(new ReactionRemoved($reactionId, $messageId, $conversationId))->toOthers();

        return response()->json([
            'message' => 'Reaction removed successfully',
        ]);
    }
}
