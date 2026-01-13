<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bot;
use App\Models\BotInstallation;
use App\Models\BotToken;
use App\Models\SlashCommand;
use App\Models\Workspace;
use App\Models\User;

class GitLabBotSeeder extends Seeder
{
    public function run(): void
    {
        // Define configuration schema for the GitLab Bot
        $configSchema = [
            "fields" => [
                [
                    "name" => "gitlab_url",
                    "type" => "url",
                    "label" => "GitLab URL",
                    "description" => "Your GitLab instance URL (e.g., https://gitlab.com or https://gitlab.yourcompany.com)",
                    "required" => true,
                    "placeholder" => "https://gitlab.com",
                ],
                [
                    "name" => "api_token",
                    "type" => "secret",
                    "label" => "API Token",
                    "description" => "Personal access token with api scope (Settings → Access Tokens)",
                    "required" => true,
                ],
                [
                    "name" => "default_project",
                    "type" => "string",
                    "label" => "Default Project",
                    "description" => "Default project path for commands (e.g., group/project-name)",
                    "required" => false,
                    "placeholder" => "mygroup/myproject",
                ],
                [
                    "name" => "notify_on_push",
                    "type" => "boolean",
                    "label" => "Push Notifications",
                    "description" => "Notify when code is pushed to the repository",
                    "required" => false,
                    "default" => true,
                ],
                [
                    "name" => "notify_on_mr",
                    "type" => "boolean",
                    "label" => "Merge Request Notifications",
                    "description" => "Notify when merge requests are opened, merged, or closed",
                    "required" => false,
                    "default" => true,
                ],
                [
                    "name" => "notify_on_pipeline",
                    "type" => "boolean",
                    "label" => "Pipeline Notifications",
                    "description" => "Notify when pipelines succeed or fail",
                    "required" => false,
                    "default" => false,
                ],
            ],
        ];

        // Create the GitLab Bot
        $bot = Bot::updateOrCreate(
            ["slug" => "gitlab-bot"],
            [
                "name" => "GitLab Bot",
                "description" => "Integrate with GitLab - view projects, issues, merge requests, pipelines, and commits",
                "avatar_url" => null,
                "webhook_url" => "http://app:8002",
                "config_schema" => $configSchema,
                "is_active" => true,
                "created_by_user_id" => User::first()->id ?? null,
            ]
        );

        echo "✅ GitLab Bot created: {$bot->name}\n";

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
                    "config" => [
                        "notify_on_push" => true,
                        "notify_on_mr" => true,
                        "notify_on_pipeline" => false,
                    ],
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

            // Only show token if newly created
            if ($token->wasRecentlyCreated) {
                echo "🔑 Bot token: {$plainToken}\n";
                echo "   (Save this token - you will need it to run the bot)\n\n";
            }

            // Register slash command
            SlashCommand::firstOrCreate(
                [
                    "bot_installation_id" => $installation->id,
                    "command" => "gitlab",
                ],
                [
                    "description" => "GitLab integration - projects, issues, MRs, pipelines, commits",
                    "usage_hint" => "/gitlab [projects|issues|mrs|pipeline|commits|help] [project]",
                    "is_active" => true,
                ]
            );

            echo "✅ Slash command registered: /gitlab\n\n";
        }

        echo "🎉 GitLab Bot setup complete!\n";
        echo "\nTo start the bot, run:\n";
        echo "  BOT_TOKEN=<token> php bots/gitlab-bot/server.php\n\n";
    }
}
