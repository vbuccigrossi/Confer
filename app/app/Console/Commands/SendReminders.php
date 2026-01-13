<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Models\BotInstallation;
use App\Models\BotToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reminders:send {--dry-run : Show what would be sent without actually sending}';

    /**
     * The console command description.
     */
    protected $description = 'Send due reminders to users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $pendingReminders = Reminder::getPendingReminders();

        if ($pendingReminders->isEmpty()) {
            $this->info('No pending reminders to send.');
            return 0;
        }

        $this->info("Found {$pendingReminders->count()} pending reminder(s).");

        foreach ($pendingReminders as $reminder) {
            $this->processReminder($reminder, $dryRun);
        }

        return 0;
    }

    /**
     * Process a single reminder.
     */
    private function processReminder(Reminder $reminder, bool $dryRun): void
    {
        $targetUser = $reminder->targetUser ?? $reminder->creator;
        $conversationId = $reminder->conversation_id;
        $workspaceId = $reminder->workspace_id;

        // Build the reminder message
        $message = "⏰ **Reminder**\n\n";
        $message .= "Hey {$targetUser->name}! You asked to be reminded:\n\n";
        $message .= "> {$reminder->message}\n\n";
        
        if ($reminder->recurrence) {
            $message .= "_This is a recurring {$reminder->recurrence} reminder._";
        }

        $this->line("  Reminder #{$reminder->id}: \"{$reminder->message}\"");
        $this->line("    To: {$targetUser->name}");
        $this->line("    Conversation: #{$conversationId}");

        if ($dryRun) {
            $this->info("    [DRY RUN] Would send message");
            return;
        }

        // Find the Reminder Bot installation for this workspace
        $installation = BotInstallation::whereHas('bot', function ($q) {
            $q->where('slug', 'reminder-bot');
        })
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->first();

        if (!$installation) {
            $this->error("    No Reminder Bot installation found for workspace #{$workspaceId}");
            Log::warning("SendReminders: No bot installation for workspace {$workspaceId}");
            return;
        }

        // Get bot token
        $tokenRecord = BotToken::where('bot_installation_id', $installation->id)->first();
        
        if (!$tokenRecord) {
            $this->error("    No bot token found for installation #{$installation->id}");
            Log::warning("SendReminders: No token for installation {$installation->id}");
            return;
        }

        // We need to find the plain token - but we only store hashed tokens
        // The bot server needs to use its token directly
        // Instead, we'll use internal API or direct DB message creation
        
        $success = $this->sendBotMessage($conversationId, $message, $installation);

        if ($success) {
            $reminder->markAsSent();
            $this->info("    ✓ Sent and marked as complete");
        } else {
            $this->error("    ✗ Failed to send");
        }
    }

    /**
     * Send a message as the bot.
     */
    private function sendBotMessage(int $conversationId, string $message, BotInstallation $installation): bool
    {
        try {
            // Create message directly in database as the bot
            $botUserId = $installation->bot->created_by_user_id;
            
            // We'll use the Message model directly
            \App\Models\Message::create([
                'conversation_id' => $conversationId,
                'user_id' => $botUserId,
                'content' => $message,
                'type' => 'text',
            ]);

            // Broadcast the message event
            $conversation = \App\Models\Conversation::find($conversationId);
            if ($conversation) {
                broadcast(new \App\Events\MessageSent(
                    \App\Models\Message::where('conversation_id', $conversationId)->latest()->first()
                ))->toOthers();
            }

            return true;
        } catch (\Exception $e) {
            Log::error("SendReminders: Failed to send message - " . $e->getMessage());
            $this->error("    Error: " . $e->getMessage());
            return false;
        }
    }
}
