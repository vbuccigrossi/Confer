<?php

namespace App\Services;

use App\Models\SlashCommand;
use App\Models\Workspace;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SlashCommandService
{
    /**
     * Check if a message starts with a slash command
     */
    public function isSlashCommand(string $message): bool
    {
        return str_starts_with(trim($message), '/' );
    }

    /**
     * Parse slash command from message
     */
    public function parseCommand(string $message): array
    {
        $message = trim($message);
        
        // Extract command and arguments
        // Example: "/news tech" -> command: "news", args: "tech"
        preg_match('/^\/(\w+)\s*(.*)?$/s', $message, $matches);
        
        return [
            'command' => $matches[1] ?? null,
            'args' => trim($matches[2] ?? ''),
        ];
    }

    /**
     * Execute a slash command
     */
    public function executeCommand(
        string $command,
        string $args,
        int $workspaceId,
        int $conversationId,
        int $userId
    ): ?array {
        // Check for native (built-in) commands first
        if ($this->isNativeCommand($command)) {
            return $this->executeNativeCommand($command, $args, $workspaceId, $conversationId, $userId);
        }

        // Find the slash command in the workspace
        $slashCommand = SlashCommand::whereHas('installation', function ($query) use ($workspaceId) {
            $query->where('workspace_id', $workspaceId)
                  ->where('is_active', true);
        })
        ->where('command', $command)
        ->where('is_active', true)
        ->with('installation.bot')
        ->first();

        if (!$slashCommand) {
            return [
                'success' => false,
                'error' => "Unknown command: /{$command}",
            ];
        }

        $bot = $slashCommand->installation->bot;

        // Check if bot has a webhook URL
        if (!$bot->webhook_url) {
            Log::error("Bot {$bot->name} has no webhook URL configured");
            return [
                'success' => false,
                'error' => 'Bot is not properly configured',
            ];
        }

        try {
            // Get the installation config to pass to the bot
            $installationConfig = $slashCommand->installation->config ?? [];

            // Send webhook to bot
            $response = Http::timeout(10)
                ->post($bot->webhook_url, [
                    'type' => 'slash_command',
                    'command' => $command,
                    'args' => $args,
                    'workspace_id' => $workspaceId,
                    'conversation_id' => $conversationId,
                    'user_id' => $userId,
                    'config' => $installationConfig,
                    'timestamp' => now()->toIso8601String(),
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'response' => $response->json(),
                ];
            }

            Log::error("Bot webhook failed", [
                'bot' => $bot->name,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => 'Bot webhook failed',
            ];

        } catch (\Exception $e) {
            Log::error("Slash command execution failed", [
                'command' => $command,
                'bot' => $bot->name,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Command execution failed',
            ];
        }
    }

    /**
     * Get available commands for a workspace
     */
    public function getAvailableCommands(int $workspaceId): array
    {
        $commands = SlashCommand::whereHas('installation', function ($query) use ($workspaceId) {
            $query->where('workspace_id', $workspaceId)
                  ->where('is_active', true);
        })
        ->where('is_active', true)
        ->with('installation.bot')
        ->get()
        ->map(function ($cmd) {
            return [
                'command' => '/'. $cmd->command,
                'description' => $cmd->description,
                'usage_hint' => $cmd->usage_hint,
                'bot_name' => $cmd->installation->bot->name,
            ];
        })
        ->toArray();

        // Add native commands
        $nativeCommands = [
            ['command' => '/help', 'description' => 'Show all available commands', 'usage_hint' => '/help', 'bot_name' => 'System'],
            ['command' => '/listbots', 'description' => 'List all available bots', 'usage_hint' => '/listbots', 'bot_name' => 'System'],
            ['command' => '/addbot', 'description' => 'Add a bot to this conversation', 'usage_hint' => '/addbot <bot_name>', 'bot_name' => 'System'],
            ['command' => '/removebot', 'description' => 'Remove a bot from this conversation', 'usage_hint' => '/removebot <bot_name>', 'bot_name' => 'System'],
            ['command' => '/listusers', 'description' => 'List users in this conversation', 'usage_hint' => '/listusers', 'bot_name' => 'System'],
            ['command' => '/adduser', 'description' => 'Add a user to this conversation', 'usage_hint' => '/adduser <username>', 'bot_name' => 'System'],
            ['command' => '/removeuser', 'description' => 'Remove a user from this conversation', 'usage_hint' => '/removeuser <username>', 'bot_name' => 'System'],
        ];

        return array_merge($nativeCommands, $commands);
    }

    /**
     * Check if a command is a native (built-in) command
     */
    private function isNativeCommand(string $command): bool
    {
        return in_array($command, ['help', 'listbots', 'addbot', 'removebot', 'listusers', 'adduser', 'removeuser']);
    }

    /**
     * Execute a native command
     */
    private function executeNativeCommand(
        string $command,
        string $args,
        int $workspaceId,
        int $conversationId,
        int $userId
    ): array {
        return match ($command) {
            'help' => $this->handleHelp($workspaceId),
            'listbots' => $this->handleListBots($workspaceId, $conversationId),
            'addbot' => $this->handleAddBot($args, $workspaceId, $conversationId, $userId),
            'removebot' => $this->handleRemoveBot($args, $workspaceId, $conversationId, $userId),
            'listusers' => $this->handleListUsers($conversationId),
            'adduser' => $this->handleAddUser($args, $workspaceId, $conversationId, $userId),
            'removeuser' => $this->handleRemoveUser($args, $conversationId, $userId),
            default => ['success' => false, 'error' => 'Unknown command'],
        };
    }

    private function handleHelp(int $workspaceId): array
    {
        $commands = $this->getAvailableCommands($workspaceId);

        $helpText = "**📚 Available Commands:**\n\n";

        foreach ($commands as $cmd) {
            $helpText .= "**{$cmd['command']}**\n";
            $helpText .= "  {$cmd['description']}\n";
            $helpText .= "  Usage: `{$cmd['usage_hint']}`\n";
            if ($cmd['bot_name'] !== 'System') {
                $helpText .= "  Provided by: {$cmd['bot_name']}\n";
            }
            $helpText .= "\n";
        }

        return ['success' => true, 'message' => $helpText];
    }

    private function handleListBots(int $workspaceId, int $conversationId): array
    {
        $bots = \App\Models\User::where('email', 'LIKE', '%@bots.local')->get();

        if ($bots->isEmpty()) {
            return ['success' => true, 'message' => 'No bots available.'];
        }

        $botList = $bots->map(fn($bot) => "• {$bot->name}")->join("\n");
        return ['success' => true, 'message' => "**Available Bots:**\n{$botList}"];
    }

    private function handleAddBot(string $args, int $workspaceId, int $conversationId, int $userId): array
    {
        $botName = trim($args);
        if (empty($botName)) {
            return ['success' => false, 'error' => 'Usage: /addbot <bot_name>'];
        }

        // Find bot by name
        $bot = \App\Models\User::where('email', 'LIKE', '%@bots.local')
            ->where('name', 'LIKE', "%{$botName}%")
            ->first();

        if (!$bot) {
            return ['success' => false, 'error' => "Bot '{$botName}' not found. Use /listbots to see available bots."];
        }

        $conversation = \App\Models\Conversation::find($conversationId);

        // Check if bot is already in conversation
        if ($conversation->members()->where('user_id', $bot->id)->exists()) {
            return ['success' => false, 'error' => "{$bot->name} is already in this conversation."];
        }

        // Add bot to conversation
        $conversation->members()->create([
            'user_id' => $bot->id,
            'role' => 'member',
        ]);

        return ['success' => true, 'message' => "✅ Added {$bot->name} to the conversation."];
    }

    private function handleRemoveBot(string $args, int $workspaceId, int $conversationId, int $userId): array
    {
        $botName = trim($args);
        if (empty($botName)) {
            return ['success' => false, 'error' => 'Usage: /removebot <bot_name>'];
        }

        // Find bot by name
        $bot = \App\Models\User::where('email', 'LIKE', '%@bots.local')
            ->where('name', 'LIKE', "%{$botName}%")
            ->first();

        if (!$bot) {
            return ['success' => false, 'error' => "Bot '{$botName}' not found."];
        }

        $conversation = \App\Models\Conversation::find($conversationId);

        // Check if bot is in conversation
        $member = $conversation->members()->where('user_id', $bot->id)->first();
        if (!$member) {
            return ['success' => false, 'error' => "{$bot->name} is not in this conversation."];
        }

        // Remove bot from conversation
        $member->delete();

        return ['success' => true, 'message' => "✅ Removed {$bot->name} from the conversation."];
    }

    private function handleListUsers(int $conversationId): array
    {
        $conversation = \App\Models\Conversation::with('members.user')->find($conversationId);

        $users = $conversation->members->map(function($member) {
            $user = $member->user;
            $isBot = str_ends_with($user->email, '@bots.local');
            $icon = $isBot ? '🤖' : '👤';
            return "{$icon} {$user->name}";
        })->join("\n");

        return ['success' => true, 'message' => "**Conversation Members:**\n{$users}"];
    }

    private function handleAddUser(string $args, int $workspaceId, int $conversationId, int $userId): array
    {
        $username = trim($args);
        if (empty($username)) {
            return ['success' => false, 'error' => 'Usage: /adduser <username>'];
        }

        // Find user by name in the workspace
        $user = \App\Models\User::whereHas('workspaces', function ($q) use ($workspaceId) {
            $q->where('workspaces.id', $workspaceId);
        })
        ->where('name', 'LIKE', "%{$username}%")
        ->where('email', 'NOT LIKE', '%@bots.local')
        ->first();

        if (!$user) {
            return ['success' => false, 'error' => "User '{$username}' not found in this workspace."];
        }

        $conversation = \App\Models\Conversation::find($conversationId);

        // Check if user is already in conversation
        if ($conversation->members()->where('user_id', $user->id)->exists()) {
            return ['success' => false, 'error' => "{$user->name} is already in this conversation."];
        }

        // Add user to conversation
        $conversation->members()->create([
            'user_id' => $user->id,
            'role' => 'member',
        ]);

        return ['success' => true, 'message' => "✅ Added {$user->name} to the conversation."];
    }

    private function handleRemoveUser(string $args, int $conversationId, int $userId): array
    {
        $username = trim($args);
        if (empty($username)) {
            return ['success' => false, 'error' => 'Usage: /removeuser <username>'];
        }

        // Find user by name
        $user = \App\Models\User::where('name', 'LIKE', "%{$username}%")
            ->where('email', 'NOT LIKE', '%@bots.local')
            ->first();

        if (!$user) {
            return ['success' => false, 'error' => "User '{$username}' not found."];
        }

        // Can't remove yourself
        if ($user->id === $userId) {
            return ['success' => false, 'error' => "You cannot remove yourself. Leave the conversation instead."];
        }

        $conversation = \App\Models\Conversation::find($conversationId);

        // Check if user is in conversation
        $member = $conversation->members()->where('user_id', $user->id)->first();
        if (!$member) {
            return ['success' => false, 'error' => "{$user->name} is not in this conversation."];
        }

        // Remove user from conversation
        $member->delete();

        return ['success' => true, 'message' => "✅ Removed {$user->name} from the conversation."];
    }
}
