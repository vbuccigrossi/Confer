<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bot;
use App\Models\BotInstallation;
use App\Models\BotToken;
use App\Models\SlashCommand;
use App\Models\Workspace;
use App\Models\User;

class ReminderBotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create the Reminder Bot (no config needed)
        $bot = Bot::firstOrCreate(
            ["slug" => "reminder-bot"],
            [
                "name" => "Reminder Bot",
                "description" => "Set reminders with natural language. Get notified in chat when it's time.",
                "avatar_url" => null,
                "webhook_url" => "http://app:8003", // Bot server will listen here
                "config_schema" => null,
                "is_active" => true,
                "created_by_user_id" => User::first()->id ?? null,
            ]
        );

        echo "✅ Reminder Bot created: {$bot->name}\n";

        // Install bot in all workspaces
        $workspaces = Workspace::all();

        foreach ($workspaces as $workspace) {
            $installation = BotInstallation::firstOrCreate(
                [
                    "bot_id" => $bot->id,
                    "workspace_id" => $workspace->id,
                ],
                [
                    "installed_by_user_id" => $workspace->owner_id,
                    "config" => [],
                    "is_active" => true,
                    "installed_at" => now(),
                ]
            );

            echo "✅ Bot installed in workspace: {$workspace->name}\n";

            // Create bot token
            $plainToken = BotToken::generateToken();
            $hashedToken = hash("sha256", $plainToken);

            $token = BotToken::firstOrCreate(
                ["bot_installation_id" => $installation->id],
                [
                    "token" => $hashedToken,
                    "name" => "Default Token",
                    "scopes" => ["messages:write", "messages:read"],
                    "expires_at" => null,
                ]
            );

            echo "🔑 Bot token: {$plainToken}\n";
            echo "   (Save this token - you will need it to run the bot)\n\n";

            // Register slash command
            SlashCommand::firstOrCreate(
                [
                    "bot_installation_id" => $installation->id,
                    "command" => "remind",
                ],
                [
                    "description" => "Set a reminder with natural language",
                    "usage_hint" => "/remind me in 30 minutes to check the build",
                    "is_active" => true,
                ]
            );

            echo "✅ Slash command registered: /remind\n\n";
        }

        echo "🎉 Reminder Bot setup complete!\n";
        echo "\nTo start the bot, run:\n";
        echo "  BOT_TOKEN=<token> php bots/reminder-bot/server.php\n\n";
    }
}
