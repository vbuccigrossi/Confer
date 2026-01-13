<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bot;
use App\Models\BotInstallation;
use App\Models\BotToken;
use App\Models\SlashCommand;
use App\Models\Workspace;
use App\Models\User;
use Illuminate\Support\Str;

class NewsBotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define configuration schema for the News Bot
        $configSchema = [
            "fields" => [
                [
                    "name" => "api_key",
                    "type" => "secret",
                    "label" => "News API Key",
                    "description" => "API key from newsapi.org (optional - uses demo key if not provided)",
                    "required" => false,
                    "placeholder" => "your-api-key-here",
                ],
                [
                    "name" => "default_category",
                    "type" => "select",
                    "label" => "Default Category",
                    "description" => "Default news category when none is specified",
                    "required" => false,
                    "default" => "general",
                    "options" => [
                        ["value" => "general", "label" => "General"],
                        ["value" => "technology", "label" => "Technology"],
                        ["value" => "business", "label" => "Business"],
                        ["value" => "science", "label" => "Science"],
                        ["value" => "health", "label" => "Health"],
                        ["value" => "sports", "label" => "Sports"],
                        ["value" => "entertainment", "label" => "Entertainment"],
                    ],
                ],
                [
                    "name" => "max_headlines",
                    "type" => "number",
                    "label" => "Max Headlines",
                    "description" => "Maximum number of headlines to show (1-10)",
                    "required" => false,
                    "default" => 5,
                ],
                [
                    "name" => "show_descriptions",
                    "type" => "boolean",
                    "label" => "Show Descriptions",
                    "description" => "Include article descriptions in the response",
                    "required" => false,
                    "default" => true,
                ],
            ],
        ];

        // Create the News Bot
        $bot = Bot::firstOrCreate(
            ["slug" => "news-bot"],
            [
                "name" => "News Bot",
                "description" => "Fetches latest news headlines on demand",
                "avatar_url" => null,
                "webhook_url" => "http://app:8001", // Bot server will listen here
                "config_schema" => $configSchema,
                "is_active" => true,
                "created_by_user_id" => User::first()->id ?? null,
            ]
        );

        // Update config_schema if bot already exists (for re-running seeder)
        if (!$bot->wasRecentlyCreated && empty($bot->config_schema)) {
            $bot->update(["config_schema" => $configSchema]);
            echo "📝 Updated News Bot with config schema\n";
        }

        echo "✅ News Bot created: {$bot->name}\n";

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
                    "command" => "news",
                ],
                [
                    "description" => "Fetch latest news headlines",
                    "usage_hint" => "/news [category]",
                    "is_active" => true,
                ]
            );

            echo "✅ Slash command registered: /news\n\n";
        }

        echo "🎉 News Bot setup complete!\n";
        echo "\nTo start the bot, run:\n";
        echo "  BOT_TOKEN=<token> php bots/news-bot/server.php\n\n";
    }
}
