<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\BotInstallation;
use App\Models\BotToken;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handle incoming webhooks from GitLab
 *
 * GitLab sends webhooks for various events like pushes, merge requests,
 * pipeline status changes, etc. This controller processes these events
 * and posts notifications to configured channels.
 */
class GitLabWebhookController extends Controller
{
    /**
     * Handle incoming GitLab webhook
     *
     * URL: /webhooks/gitlab/{installationId}
     * The installationId ties the webhook to a specific workspace's bot installation
     */
    public function handle(Request $request, int $installationId): JsonResponse
    {
        // Find the bot installation
        $installation = BotInstallation::with(['bot', 'workspace'])
            ->where('id', $installationId)
            ->whereHas('bot', fn($q) => $q->where('slug', 'gitlab-bot'))
            ->first();

        if (!$installation) {
            Log::warning('GitLab webhook received for unknown installation', [
                'installation_id' => $installationId,
            ]);
            return response()->json(['error' => 'Installation not found'], 404);
        }

        // Validate webhook secret if configured
        $config = $installation->config ?? [];
        $webhookSecret = $config['webhook_secret'] ?? null;

        if ($webhookSecret) {
            $gitlabToken = $request->header('X-Gitlab-Token');
            if ($gitlabToken !== $webhookSecret) {
                Log::warning('GitLab webhook secret mismatch', [
                    'installation_id' => $installationId,
                ]);
                return response()->json(['error' => 'Invalid webhook secret'], 401);
            }
        }

        // Get the event type
        $eventType = $request->header('X-Gitlab-Event');
        $payload = $request->all();

        Log::info('GitLab webhook received', [
            'installation_id' => $installationId,
            'event_type' => $eventType,
            'project' => $payload['project']['path_with_namespace'] ?? 'unknown',
        ]);

        // Process the event
        $message = $this->processEvent($eventType, $payload, $config);

        if ($message) {
            $this->postToChannel($installation, $message, $config);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Process GitLab event and generate notification message
     */
    private function processEvent(string $eventType, array $payload, array $config): ?string
    {
        return match ($eventType) {
            'Push Hook' => $this->processPushEvent($payload, $config),
            'Merge Request Hook' => $this->processMergeRequestEvent($payload, $config),
            'Pipeline Hook' => $this->processPipelineEvent($payload, $config),
            'Issue Hook' => $this->processIssueEvent($payload, $config),
            'Note Hook' => $this->processNoteEvent($payload, $config),
            'Tag Push Hook' => $this->processTagEvent($payload, $config),
            default => null,
        };
    }

    /**
     * Process push event
     */
    private function processPushEvent(array $payload, array $config): ?string
    {
        if (!($config['notify_on_push'] ?? true)) {
            return null;
        }

        $project = $payload['project']['path_with_namespace'] ?? 'Unknown';
        $branch = str_replace('refs/heads/', '', $payload['ref'] ?? '');
        $user = $payload['user_name'] ?? 'Someone';
        $commits = $payload['commits'] ?? [];
        $totalCommits = $payload['total_commits_count'] ?? count($commits);

        if ($totalCommits === 0) {
            return null; // Branch deletion or no commits
        }

        $commitWord = $totalCommits === 1 ? 'commit' : 'commits';
        $msg = "📦 **Push to {$project}**\n\n";
        $msg .= "**{$user}** pushed {$totalCommits} {$commitWord} to `{$branch}`\n\n";

        // Show up to 3 commits
        $shownCommits = array_slice($commits, 0, 3);
        foreach ($shownCommits as $commit) {
            $shortSha = substr($commit['id'], 0, 8);
            $message = strlen($commit['message']) > 60
                ? substr($commit['message'], 0, 57) . '...'
                : trim($commit['message']);
            $msg .= "• `{$shortSha}` {$message}\n";
        }

        if ($totalCommits > 3) {
            $remaining = $totalCommits - 3;
            $msg .= "• _...and {$remaining} more_\n";
        }

        $compareUrl = $payload['compare_url'] ?? $payload['project']['web_url'] ?? null;
        if ($compareUrl) {
            $msg .= "\n🔗 [View changes]({$compareUrl})";
        }

        return $msg;
    }

    /**
     * Process merge request event
     */
    private function processMergeRequestEvent(array $payload, array $config): ?string
    {
        if (!($config['notify_on_mr'] ?? true)) {
            return null;
        }

        $attrs = $payload['object_attributes'] ?? [];
        $action = $attrs['action'] ?? $attrs['state'] ?? 'updated';
        $project = $payload['project']['path_with_namespace'] ?? 'Unknown';
        $user = $payload['user']['name'] ?? 'Someone';
        $title = $attrs['title'] ?? 'Untitled';
        $iid = $attrs['iid'] ?? '?';
        $sourceBranch = $attrs['source_branch'] ?? '';
        $targetBranch = $attrs['target_branch'] ?? '';
        $url = $attrs['url'] ?? '';

        // Only notify on specific actions
        $notifyActions = ['open', 'opened', 'merge', 'merged', 'close', 'closed', 'reopen', 'reopened', 'approved', 'unapproved'];
        if (!in_array($action, $notifyActions)) {
            return null;
        }

        $emoji = match ($action) {
            'open', 'opened' => '🆕',
            'merge', 'merged' => '✅',
            'close', 'closed' => '❌',
            'reopen', 'reopened' => '🔄',
            'approved' => '👍',
            'unapproved' => '👎',
            default => '🔀',
        };

        $actionText = match ($action) {
            'open', 'opened' => 'opened',
            'merge', 'merged' => 'merged',
            'close', 'closed' => 'closed',
            'reopen', 'reopened' => 'reopened',
            'approved' => 'approved',
            'unapproved' => 'unapproved',
            default => $action,
        };

        $msg = "{$emoji} **Merge Request {$actionText}** in {$project}\n\n";
        $msg .= "**!{$iid}** {$title}\n";
        $msg .= "`{$sourceBranch}` → `{$targetBranch}`\n";
        $msg .= "👤 {$user}\n";

        if ($url) {
            $msg .= "\n🔗 [View MR]({$url})";
        }

        return $msg;
    }

    /**
     * Process pipeline event
     */
    private function processPipelineEvent(array $payload, array $config): ?string
    {
        if (!($config['notify_on_pipeline'] ?? false)) {
            return null;
        }

        $attrs = $payload['object_attributes'] ?? [];
        $status = $attrs['status'] ?? 'unknown';
        $project = $payload['project']['path_with_namespace'] ?? 'Unknown';
        $ref = $attrs['ref'] ?? '';
        $pipelineId = $attrs['id'] ?? '?';

        // Only notify on final states
        if (!in_array($status, ['success', 'failed', 'canceled'])) {
            return null;
        }

        $emoji = match ($status) {
            'success' => '✅',
            'failed' => '❌',
            'canceled' => '🚫',
            default => '❓',
        };

        $statusText = match ($status) {
            'success' => 'passed',
            'failed' => 'failed',
            'canceled' => 'was canceled',
            default => $status,
        };

        $msg = "{$emoji} **Pipeline {$statusText}** in {$project}\n\n";
        $msg .= "Pipeline **#{$pipelineId}** on `{$ref}` {$statusText}\n";

        // Include failed jobs if pipeline failed
        if ($status === 'failed' && !empty($payload['builds'])) {
            $failedJobs = array_filter($payload['builds'], fn($b) => $b['status'] === 'failed');
            if (!empty($failedJobs)) {
                $jobNames = array_map(fn($j) => $j['name'], array_slice($failedJobs, 0, 3));
                $msg .= "Failed jobs: " . implode(', ', $jobNames) . "\n";
            }
        }

        $webUrl = $payload['project']['web_url'] ?? '';
        if ($webUrl) {
            $msg .= "\n🔗 [View Pipeline]({$webUrl}/-/pipelines/{$pipelineId})";
        }

        return $msg;
    }

    /**
     * Process issue event
     */
    private function processIssueEvent(array $payload, array $config): ?string
    {
        if (!($config['notify_on_issues'] ?? false)) {
            return null;
        }

        $attrs = $payload['object_attributes'] ?? [];
        $action = $attrs['action'] ?? 'updated';
        $project = $payload['project']['path_with_namespace'] ?? 'Unknown';
        $user = $payload['user']['name'] ?? 'Someone';
        $title = $attrs['title'] ?? 'Untitled';
        $iid = $attrs['iid'] ?? '?';
        $url = $attrs['url'] ?? '';

        if (!in_array($action, ['open', 'close', 'reopen'])) {
            return null;
        }

        $emoji = match ($action) {
            'open' => '📋',
            'close' => '✅',
            'reopen' => '🔄',
            default => '📋',
        };

        $msg = "{$emoji} **Issue {$action}ed** in {$project}\n\n";
        $msg .= "**#{$iid}** {$title}\n";
        $msg .= "👤 {$user}\n";

        if ($url) {
            $msg .= "\n🔗 [View Issue]({$url})";
        }

        return $msg;
    }

    /**
     * Process comment/note event
     */
    private function processNoteEvent(array $payload, array $config): ?string
    {
        if (!($config['notify_on_comments'] ?? false)) {
            return null;
        }

        $attrs = $payload['object_attributes'] ?? [];
        $project = $payload['project']['path_with_namespace'] ?? 'Unknown';
        $user = $payload['user']['name'] ?? 'Someone';
        $noteableType = $attrs['noteable_type'] ?? '';
        $note = $attrs['note'] ?? '';
        $url = $attrs['url'] ?? '';

        $typeLabel = match ($noteableType) {
            'MergeRequest' => 'merge request',
            'Issue' => 'issue',
            'Commit' => 'commit',
            'Snippet' => 'snippet',
            default => 'item',
        };

        // Truncate note
        $notePreview = strlen($note) > 100 ? substr($note, 0, 97) . '...' : $note;

        $msg = "💬 **New comment** on {$typeLabel} in {$project}\n\n";
        $msg .= "**{$user}**: {$notePreview}\n";

        if ($url) {
            $msg .= "\n🔗 [View comment]({$url})";
        }

        return $msg;
    }

    /**
     * Process tag push event
     */
    private function processTagEvent(array $payload, array $config): ?string
    {
        if (!($config['notify_on_tags'] ?? false)) {
            return null;
        }

        $project = $payload['project']['path_with_namespace'] ?? 'Unknown';
        $user = $payload['user_name'] ?? 'Someone';
        $ref = str_replace('refs/tags/', '', $payload['ref'] ?? '');
        $isDeleted = ($payload['after'] ?? '') === '0000000000000000000000000000000000000000';

        if ($isDeleted) {
            return "🏷️ **Tag deleted** in {$project}\n\nTag `{$ref}` was deleted by {$user}";
        }

        $msg = "🏷️ **New tag** in {$project}\n\n";
        $msg .= "**{$user}** created tag `{$ref}`\n";

        $webUrl = $payload['project']['web_url'] ?? '';
        if ($webUrl) {
            $msg .= "\n🔗 [View tag]({$webUrl}/-/tags/{$ref})";
        }

        return $msg;
    }

    /**
     * Post message to configured channel
     */
    private function postToChannel(BotInstallation $installation, string $message, array $config): void
    {
        // Get the channel to post to
        $channelId = $config['notify_channel_id'] ?? null;

        if (!$channelId) {
            // Try to find the first public channel in the workspace
            $channel = Conversation::where('workspace_id', $installation->workspace_id)
                ->where('type', 'public_channel')
                ->first();

            if (!$channel) {
                Log::warning('GitLab webhook: No channel configured and no public channel found', [
                    'installation_id' => $installation->id,
                ]);
                return;
            }
            $channelId = $channel->id;
        }

        // Find the bot user
        $botUser = \App\Models\User::where('email', 'like', 'bot_%@bots.local')
            ->whereHas('conversations', fn($q) => $q->where('conversations.id', $channelId))
            ->first();

        if (!$botUser) {
            // Try to find any bot user associated with this bot installation
            $botUser = \App\Models\User::where('name', $installation->bot->name)->first();
        }

        if (!$botUser) {
            Log::warning('GitLab webhook: Bot user not found', [
                'installation_id' => $installation->id,
                'bot_name' => $installation->bot->name,
            ]);
            return;
        }

        // Create the message
        try {
            Message::create([
                'conversation_id' => $channelId,
                'user_id' => $botUser->id,
                'body_md' => $message,
                'body_html' => \Illuminate\Support\Str::markdown($message),
            ]);

            Log::info('GitLab webhook notification posted', [
                'installation_id' => $installation->id,
                'channel_id' => $channelId,
            ]);
        } catch (\Exception $e) {
            Log::error('GitLab webhook: Failed to post message', [
                'installation_id' => $installation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
