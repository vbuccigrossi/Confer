<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Workspace;
use App\Services\AuditLogService;
use App\Services\StorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class PurgeExpiredMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Workspace $workspace,
        public bool $dryRun = true,
        public ?int $actorUserId = null
    ) {
    }

    /**
     * Execute the job.
     *
     * @return array{total_messages: int, by_conversation: array<int, array{id: int, name: string, count: int}>}
     */
    public function handle(AuditLogService $auditService, StorageService $storageService): array
    {
        // Check if retention is enabled
        if ($this->workspace->message_retention_days === null) {
            return [
                'total_messages' => 0,
                'by_conversation' => [],
            ];
        }

        // Calculate cutoff date
        $cutoffDate = now()->subDays($this->workspace->message_retention_days);

        // Get messages to purge grouped by conversation
        $conversationCounts = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.workspace_id', $this->workspace->id)
            ->where('messages.created_at', '<', $cutoffDate)
            ->select(
                'conversations.id as conversation_id',
                'conversations.name as conversation_name',
                DB::raw('COUNT(messages.id) as message_count')
            )
            ->groupBy('conversations.id', 'conversations.name')
            ->get();

        $totalMessages = $conversationCounts->sum('message_count');

        $byConversation = $conversationCounts->map(function ($row) {
            return [
                'id' => $row->conversation_id,
                'name' => $row->conversation_name,
                'count' => $row->message_count,
            ];
        })->toArray();

        // If dry run, just return counts
        if ($this->dryRun) {
            return [
                'total_messages' => $totalMessages,
                'by_conversation' => $byConversation,
            ];
        }

        // Execute actual purge
        $deletedCount = 0;
        $batchSize = config('admin.purge_batch_size', 1000);

        // Get all message IDs to delete
        $messageIds = DB::table('messages')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.workspace_id', $this->workspace->id)
            ->where('messages.created_at', '<', $cutoffDate)
            ->pluck('messages.id');

        // Process in batches to avoid long locks
        foreach ($messageIds->chunk($batchSize) as $batch) {
            DB::transaction(function () use ($batch, &$deletedCount) {
                // Get attachment sizes before deleting for storage tracking
                $attachmentSizes = DB::table('attachments')
                    ->whereIn('message_id', $batch)
                    ->pluck('file_size');

                $totalAttachmentSize = $attachmentSizes->sum();

                // Delete attachments (will cascade file deletion via model events)
                DB::table('attachments')
                    ->whereIn('message_id', $batch)
                    ->delete();

                // Delete reactions
                DB::table('reactions')
                    ->whereIn('message_id', $batch)
                    ->delete();

                // Delete messages
                $deleted = DB::table('messages')
                    ->whereIn('id', $batch)
                    ->delete();

                $deletedCount += $deleted;

                // Update storage usage
                if ($totalAttachmentSize > 0) {
                    $this->workspace->decrement('storage_used_mb', (int) ceil($totalAttachmentSize / 1024 / 1024));
                }
            });
        }

        // Log the purge execution
        $actor = $this->actorUserId ? User::find($this->actorUserId) : null;

        if ($actor) {
            $auditService->logRetentionPurgeExecuted(
                $this->workspace,
                $actor,
                $deletedCount,
                $byConversation
            );
        }

        // Ensure storage_used_mb does not go negative
        if ($this->workspace->storage_used_mb < 0) {
            $this->workspace->update(['storage_used_mb' => 0]);
        }

        return [
            'total_messages' => $deletedCount,
            'by_conversation' => $byConversation,
        ];
    }
}
