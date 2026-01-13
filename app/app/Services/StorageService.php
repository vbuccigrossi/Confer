<?php

namespace App\Services;

use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Service for tracking and managing workspace storage usage
 */
class StorageService
{
    /**
     * Calculate current storage usage for a workspace
     *
     * @return int Storage usage in megabytes
     */
    public function calculateWorkspaceUsage(Workspace $workspace): int
    {
        // Sum all attachment file sizes for this workspace
        $totalBytes = DB::table('attachments')
            ->join('messages', 'attachments.message_id', '=', 'messages.id')
            ->join('conversations', 'messages.conversation_id', '=', 'conversations.id')
            ->where('conversations.workspace_id', $workspace->id)
            ->sum('attachments.file_size');

        // Convert bytes to megabytes
        return (int) ceil($totalBytes / 1024 / 1024);
    }

    /**
     * Update workspace storage usage
     */
    public function updateWorkspaceUsage(Workspace $workspace): void
    {
        $usageMb = $this->calculateWorkspaceUsage($workspace);

        $workspace->update([
            'storage_used_mb' => $usageMb,
        ]);
    }

    /**
     * Check if workspace has available quota for upload
     *
     * @param int $sizeInBytes Size of file to upload
     * @return bool True if upload would be within quota
     */
    public function hasQuotaAvailable(Workspace $workspace, int $sizeInBytes): bool
    {
        $currentUsageMb = $workspace->storage_used_mb;
        $quotaMb = $workspace->storage_quota_mb;
        $uploadSizeMb = ceil($sizeInBytes / 1024 / 1024);

        return ($currentUsageMb + $uploadSizeMb) <= $quotaMb;
    }

    /**
     * Add to workspace storage usage (optimistic update)
     *
     * @param int $sizeInBytes Size to add
     */
    public function incrementUsage(Workspace $workspace, int $sizeInBytes): void
    {
        $sizeMb = (int) ceil($sizeInBytes / 1024 / 1024);

        $workspace->increment('storage_used_mb', $sizeMb);
    }

    /**
     * Subtract from workspace storage usage
     *
     * @param int $sizeInBytes Size to subtract
     */
    public function decrementUsage(Workspace $workspace, int $sizeInBytes): void
    {
        $sizeMb = (int) ceil($sizeInBytes / 1024 / 1024);

        $workspace->decrement('storage_used_mb', $sizeMb);

        // Ensure it never goes below zero
        if ($workspace->storage_used_mb < 0) {
            $workspace->update(['storage_used_mb' => 0]);
        }
    }

    /**
     * Get storage usage percentage
     *
     * @return float Percentage of quota used (0-100)
     */
    public function getUsagePercentage(Workspace $workspace): float
    {
        if ($workspace->storage_quota_mb === 0) {
            return 0;
        }

        $percentage = ($workspace->storage_used_mb / $workspace->storage_quota_mb) * 100;

        return min($percentage, 100);
    }

    /**
     * Get remaining storage in MB
     *
     * @return int Remaining storage in megabytes
     */
    public function getRemainingStorage(Workspace $workspace): int
    {
        return max(0, $workspace->storage_quota_mb - $workspace->storage_used_mb);
    }
}
