<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bot;
use App\Models\BotInstallation;
use App\Models\Workspace;
use App\Models\User;
use App\Models\Conversation;

class ArxivNewsBotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define configuration schema for the Arxiv News Bot
        $configSchema = [
            "fields" => [
                [
                    "name" => "conversation_id",
                    "type" => "number",
                    "label" => "Target Channel ID",
                    "description" => "The channel ID where papers will be posted",
                    "required" => true,
                    "placeholder" => "Enter the conversation ID",
                ],
                [
                    "name" => "max_papers_per_run",
                    "type" => "number",
                    "label" => "Max Papers Per Run",
                    "description" => "Maximum number of papers to post per scheduled run (1-20)",
                    "required" => false,
                    "default" => 5,
                    "min" => 1,
                    "max" => 20,
                ],
                [
                    "name" => "include_summary",
                    "type" => "boolean",
                    "label" => "Include Summary",
                    "description" => "Include paper abstract/summary in the message",
                    "required" => false,
                    "default" => true,
                ],
                [
                    "name" => "filter_categories",
                    "type" => "select",
                    "label" => "Category Filter",
                    "description" => "Only post papers from specific AI categories (leave empty for all)",
                    "required" => false,
                    "multiple" => true,
                    "options" => [
                        ["value" => "cs.AI", "label" => "Artificial Intelligence"],
                        ["value" => "cs.LG", "label" => "Machine Learning"],
                        ["value" => "cs.CL", "label" => "NLP & Language"],
                        ["value" => "cs.CV", "label" => "Computer Vision"],
                        ["value" => "cs.NE", "label" => "Neural Computing"],
                        ["value" => "cs.RO", "label" => "Robotics"],
                        ["value" => "stat.ML", "label" => "Statistical ML"],
                    ],
                ],
            ],
        ];

        // Create the Arxiv News Bot
        $bot = Bot::firstOrCreate(
            ["slug" => "arxiv-news-bot"],
            [
                "name" => "Arxiv AI News Bot",
                "description" => "Automatically posts latest AI research papers from arxiv.org every morning at 6AM",
                "avatar_url" => null,
                "webhook_url" => null, // No webhook - scheduled command only
                "config_schema" => $configSchema,
                "is_active" => true,
                "created_by_user_id" => User::first()->id ?? null,
            ]
        );

        // Update config_schema if bot already exists (for re-running seeder)
        if (!$bot->wasRecentlyCreated) {
            $bot->update([
                "config_schema" => $configSchema,
                "description" => "Automatically posts latest AI research papers from arxiv.org every morning at 6AM",
            ]);
            echo "Updated Arxiv News Bot with config schema\n";
        }

        echo "Arxiv News Bot created: {$bot->name}\n";

        // Install bot in all workspaces (they need to configure the channel ID)
        $workspaces = Workspace::all();

        foreach ($workspaces as $workspace) {
            // Try to find an existing channel named "ai-news" or similar
            $defaultChannel = Conversation::where('workspace_id', $workspace->id)
                ->where(function ($q) {
                    $q->where('name', 'like', '%ai%news%')
                        ->orWhere('name', 'like', '%arxiv%')
                        ->orWhere('name', 'like', '%research%');
                })
                ->first();

            // Or just use the first public channel
            if (!$defaultChannel) {
                $defaultChannel = Conversation::where('workspace_id', $workspace->id)
                    ->where('type', 'public_channel')
                    ->first();
            }

            $installation = BotInstallation::firstOrCreate(
                [
                    "bot_id" => $bot->id,
                    "workspace_id" => $workspace->id,
                ],
                [
                    "installed_by_user_id" => $workspace->owner_id,
                    "config" => [
                        "conversation_id" => $defaultChannel?->id,
                        "max_papers_per_run" => 5,
                        "include_summary" => true,
                        "filter_categories" => [],
                    ],
                    "is_active" => true,
                    "installed_at" => now(),
                ]
            );

            echo "Bot installed in workspace: {$workspace->name}\n";

            if ($defaultChannel) {
                echo "  -> Configured to post to: #{$defaultChannel->name}\n";
            } else {
                echo "  -> WARNING: No channel configured - update via admin panel\n";
            }
        }

        echo "\nArxiv News Bot setup complete!\n";
        echo "\nThe bot runs automatically via Laravel scheduler at 6AM daily.\n";
        echo "To run manually: php artisan arxiv:fetch\n";
        echo "To test (dry run): php artisan arxiv:fetch --dry-run\n\n";
    }
}
