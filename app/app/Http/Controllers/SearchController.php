<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\SearchQueryParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    /**
     * Search messages with full-text search and filters
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:500',
            'limit' => 'integer|min:1|max:100',
            'cursor' => 'nullable|string',
        ]);

        $query = $request->input('q');
        $limit = $request->input('limit', 20);
        $cursor = $request->input('cursor');

        // Parse the search query
        $parser = new SearchQueryParser($query);

        // If no search terms/phrases, return empty results
        if ($parser->isEmpty()) {
            return response()->json([
                'results' => [],
                'next_cursor' => null,
                'has_more' => false,
            ]);
        }

        // Detect database driver
        $driver = DB::connection()->getDriverName();

        // Build the query
        $messageQuery = Message::query()
            ->with(['user:id,name', 'conversation:id,name,type'])
            ->whereNotNull('user_id'); // Exclude deleted users' messages

        // Apply full-text search based on database driver
        if ($driver === 'pgsql') {
            // PostgreSQL: Use ts_vector and ts_query
            $tsquery = $parser->toTsQuery();

            $messageQuery->select([
                'messages.*',
                DB::raw("ts_headline('english', messages.body_md, to_tsquery('english', ?), 'MaxWords=30, MinWords=15, MaxFragments=2') as snippet")
            ]);
            $messageQuery->addBinding($tsquery, 'select');

            if (!empty($tsquery)) {
                $messageQuery->whereRaw('body_tsv @@ to_tsquery(?, ?)', ['english', $tsquery]);
            }
        } else {
            // SQLite/MySQL: Use LIKE-based search
            $terms = array_merge($parser->getTerms(), $parser->getPhrases());

            if (!empty($terms)) {
                $messageQuery->where(function ($query) use ($terms) {
                    foreach ($terms as $term) {
                        // Escape LIKE wildcards to prevent LIKE injection
                        $escapedTerm = $this->escapeLike($term);
                        $query->where('body_md', 'LIKE', '%' . $escapedTerm . '%');
                    }
                });
            }

            // For SQLite, snippet is just a substring
            $messageQuery->select('messages.*');
        }

        // ACL: Restrict to conversations the user has access to
        $user = $request->user();
        
        if (!$parser->hasFilter('global') || !$this->canSearchGlobal($user)) {
            // Non-global search: only messages in conversations where user is a member
            $messageQuery->whereHas('conversation.members', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            });
        }

        // Apply filters
        $this->applyFilters($messageQuery, $parser, $user);

        // Apply cursor pagination
        if ($cursor) {
            $decoded = $this->decodeCursor($cursor);
            if ($decoded) {
                $messageQuery->where('messages.id', '<', $decoded['id']);
            }
        }

        // Order by relevance (PostgreSQL) or just recency (SQLite)
        if ($driver === 'pgsql' && !empty($tsquery)) {
            $messageQuery->orderByRaw("ts_rank(body_tsv, to_tsquery('english', ?)) DESC", [$tsquery]);
        }

        $messageQuery->orderBy('messages.created_at', 'desc')
            ->orderBy('messages.id', 'desc');

        // Fetch results (limit + 1 to check for more)
        $results = $messageQuery->limit($limit + 1)->get();

        $hasMore = $results->count() > $limit;
        if ($hasMore) {
            $results->pop();
        }

        $nextCursor = null;
        if ($hasMore && $results->isNotEmpty()) {
            $lastMessage = $results->last();
            $nextCursor = $this->encodeCursor([
                'id' => $lastMessage->id,
                'created_at' => $lastMessage->created_at->toIso8601String(),
            ]);
        }

        return response()->json([
            'results' => $results->map(function ($message) {
                return [
                    'id' => $message->id,
                    'conversation_id' => $message->conversation_id,
                    'conversation_name' => $message->conversation->name ?? null,
                    'conversation_type' => $message->conversation->type ?? null,
                    'user' => [
                        'id' => $message->user->id,
                        'name' => $message->user->name,
                    ],
                    'body_md' => $message->body_md,
                    'snippet' => $message->snippet ?? null,
                    'created_at' => $message->created_at->toIso8601String(),
                    'edited_at' => $message->edited_at?->toIso8601String(),
                ];
            }),
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ]);
    }

    /**
     * Apply query filters
     */
    protected function applyFilters($messageQuery, SearchQueryParser $parser, User $user): void
    {
        $driver = DB::connection()->getDriverName();
        $likeOperator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';

        // in: filter (conversation name or ID)
        if ($parser->hasFilter('in')) {
            $conversationRef = ltrim($parser->getFilter('in'), '#');
            // Escape LIKE wildcards to prevent LIKE injection
            $escapedRef = $this->escapeLike($conversationRef);

            $messageQuery->whereHas('conversation', function ($query) use ($conversationRef, $escapedRef, $user, $likeOperator) {
                $query->where(function ($q) use ($conversationRef, $escapedRef, $likeOperator) {
                    $q->where('name', $likeOperator, '%' . $escapedRef . '%')
                      ->orWhere('id', $conversationRef);
                });

                // Still enforce membership within the filtered conversation
                $query->whereHas('members', function ($memberQuery) use ($user) {
                    $memberQuery->where('user_id', $user->id);
                });
            });
        }

        // from: filter (user name or ID)
        if ($parser->hasFilter('from')) {
            $userRef = ltrim($parser->getFilter('from'), '@');
            // Escape LIKE wildcards to prevent LIKE injection
            $escapedUserRef = $this->escapeLike($userRef);

            $messageQuery->whereHas('user', function ($query) use ($userRef, $escapedUserRef, $likeOperator) {
                $query->where(function ($q) use ($userRef, $escapedUserRef, $likeOperator) {
                    $q->where('name', $likeOperator, '%' . $escapedUserRef . '%')
                      ->orWhere('id', $userRef);
                });
            });
        }

        // has: filter (has:file, has:link, has:code)
        if ($parser->hasFilter('has')) {
            $hasType = $parser->getFilter('has');

            if ($hasType === 'file') {
                $messageQuery->whereHas('attachments');
            } elseif ($hasType === 'link') {
                $messageQuery->where('body_md', 'LIKE', '%http%');
            } elseif ($hasType === 'code') {
                $messageQuery->where(function($q) {
                    $q->where('body_md', 'LIKE', '%```%')
                      ->orWhere('body_md', 'LIKE', '%`%');
                });
            }
        }

        // since: filter (date)
        if ($parser->hasFilter('since')) {
            try {
                $since = new \DateTime($parser->getFilter('since'));
                $messageQuery->where('messages.created_at', '>=', $since);
            } catch (\Exception $e) {
                // Invalid date, ignore filter
            }
        }

        // until: filter (date)
        if ($parser->hasFilter('until')) {
            try {
                $until = new \DateTime($parser->getFilter('until'));
                $until->setTime(23, 59, 59); // End of day
                $messageQuery->where('messages.created_at', '<=', $until);
            } catch (\Exception $e) {
                // Invalid date, ignore filter
            }
        }

        // before: filter (alias for until)
        if ($parser->hasFilter('before')) {
            try {
                $before = new \DateTime($parser->getFilter('before'));
                $before->setTime(23, 59, 59); // End of day
                $messageQuery->where('messages.created_at', '<=', $before);
            } catch (\Exception $e) {
                // Invalid date, ignore filter
            }
        }

        // after: filter (alias for since)
        if ($parser->hasFilter('after')) {
            try {
                $after = new \DateTime($parser->getFilter('after'));
                $messageQuery->where('messages.created_at', '>=', $after);
            } catch (\Exception $e) {
                // Invalid date, ignore filter
            }
        }

        // on: filter (specific date)
        if ($parser->hasFilter('on')) {
            try {
                $on = new \DateTime($parser->getFilter('on'));
                $startOfDay = clone $on;
                $startOfDay->setTime(0, 0, 0);
                $endOfDay = clone $on;
                $endOfDay->setTime(23, 59, 59);
                $messageQuery->whereBetween('messages.created_at', [$startOfDay, $endOfDay]);
            } catch (\Exception $e) {
                // Invalid date, ignore filter
            }
        }
    }

    /**
     * Check if user can perform global search
     */
    protected function canSearchGlobal(User $user): bool
    {
        // For now, only workspace owners/admins can search globally
        // This could be expanded with more sophisticated permissions
        return $user->workspaces()
            ->wherePivot('role', 'owner')
            ->orWherePivot('role', 'admin')
            ->exists();
    }

    /**
     * Encode cursor for pagination
     */
    protected function encodeCursor(array $data): string
    {
        return base64_encode(json_encode($data));
    }

    /**
     * Decode cursor from pagination
     */
    protected function decodeCursor(string $cursor): ?array
    {
        try {
            return json_decode(base64_decode($cursor), true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Escape LIKE wildcards to prevent LIKE injection attacks
     */
    protected function escapeLike(string $value): string
    {
        return str_replace(
            ['%', '_', '\\'],
            ['\\%', '\\_', '\\\\'],
            $value
        );
    }
}
