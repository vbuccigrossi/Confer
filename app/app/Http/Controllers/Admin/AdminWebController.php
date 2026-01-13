<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Invite;
use App\Models\BotInstallation;
use App\Models\Bot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminWebController extends Controller
{
    public function overview(Request $request, Workspace $workspace): Response
    {
        // Check if user is admin
        $this->authorize("viewAdmin", $workspace);

        $stats = [
            "total_members" => $workspace->members()->count(),
            "total_channels" => Conversation::where("workspace_id", $workspace->id)
                ->whereIn("type", ["public_channel", "private_channel"])
                ->count(),
            "total_messages" => Message::whereHas("conversation", function ($query) use ($workspace) {
                $query->where("workspace_id", $workspace->id);
            })->count(),
            "total_dms" => Conversation::where("workspace_id", $workspace->id)
                ->whereIn("type", ["dm", "group_dm"])
                ->count(),
        ];

        $recentMembers = WorkspaceMember::where("workspace_id", $workspace->id)
            ->with("user")
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render("Admin/Overview", [
            "workspace" => $workspace->load("members.user"),
            "stats" => $stats,
            "recentMembers" => $recentMembers,
        ]);
    }

    public function members(Request $request, Workspace $workspace): Response
    {
        $this->authorize("viewAdmin", $workspace);

        $members = WorkspaceMember::where("workspace_id", $workspace->id)
            ->with("user")
            ->orderBy("role")
            ->orderBy("joined_at", "desc")
            ->get();

        return Inertia::render("Admin/Members", [
            "workspace" => $workspace,
            "members" => $members,
        ]);
    }

    public function invites(Request $request, Workspace $workspace): Response
    {
        $this->authorize("viewAdmin", $workspace);

        $invites = Invite::where("workspace_id", $workspace->id)
            ->with("inviter:id,name,email")
            ->orderByDesc("created_at")
            ->get()
            ->map(function ($invite) {
                return [
                    "id" => $invite->id,
                    "email" => $invite->email,
                    "invite_code" => $invite->invite_code,
                    "role" => $invite->role,
                    "is_single_use" => $invite->is_single_use,
                    "max_uses" => $invite->max_uses,
                    "use_count" => $invite->use_count,
                    "expires_at" => $invite->expires_at,
                    "accepted_at" => $invite->accepted_at,
                    "created_at" => $invite->created_at,
                    "is_expired" => $invite->isExpired(),
                    "can_be_used" => $invite->canBeUsed(),
                    "inviter" => $invite->inviter ? [
                        "id" => $invite->inviter->id,
                        "name" => $invite->inviter->name,
                    ] : null,
                ];
            });

        return Inertia::render("Admin/Invites", [
            "workspace" => $workspace,
            "invites" => $invites,
        ]);
    }

    public function bots(Request $request, Workspace $workspace): Response
    {
        $this->authorize("viewAdmin", $workspace);

        $installations = BotInstallation::where("workspace_id", $workspace->id)
            ->with(["bot", "installer:id,name", "tokens", "slashCommands"])
            ->orderByDesc("installed_at")
            ->get()
            ->map(function ($installation) {
                return [
                    "id" => $installation->id,
                    "bot" => [
                        "id" => $installation->bot->id,
                        "name" => $installation->bot->name,
                        "slug" => $installation->bot->slug,
                        "description" => $installation->bot->description,
                        "avatar_url" => $installation->bot->avatar_url,
                        "config_schema" => $installation->bot->config_schema,
                        "requires_configuration" => $installation->bot->requiresConfiguration(),
                    ],
                    "config" => $installation->config,
                    "is_active" => $installation->is_active,
                    "installed_at" => $installation->installed_at,
                    "installer" => $installation->installer ? [
                        "id" => $installation->installer->id,
                        "name" => $installation->installer->name,
                    ] : null,
                    "tokens_count" => $installation->tokens->count(),
                    "has_active_token" => $installation->tokens->where("expires_at", ">", now())->count() > 0
                        || $installation->tokens->whereNull("expires_at")->count() > 0,
                    "slash_commands" => $installation->slashCommands->map(function ($cmd) {
                        return [
                            "id" => $cmd->id,
                            "command" => $cmd->command,
                            "description" => $cmd->description,
                            "is_active" => $cmd->is_active,
                        ];
                    }),
                ];
            });

        $availableBots = Bot::where("is_active", true)
            ->whereNotIn("id", BotInstallation::where("workspace_id", $workspace->id)->pluck("bot_id"))
            ->with("creator:id,name")
            ->get()
            ->map(function ($bot) {
                return [
                    "id" => $bot->id,
                    "name" => $bot->name,
                    "slug" => $bot->slug,
                    "description" => $bot->description,
                    "avatar_url" => $bot->avatar_url,
                    "config_schema" => $bot->config_schema,
                    "requires_configuration" => $bot->requiresConfiguration(),
                    "creator" => $bot->creator ? [
                        "id" => $bot->creator->id,
                        "name" => $bot->creator->name,
                    ] : null,
                ];
            });

        return Inertia::render("Admin/Bots", [
            "workspace" => $workspace,
            "installations" => $installations,
            "availableBots" => $availableBots,
        ]);
    }

    public function settings(Request $request, Workspace $workspace): Response
    {
        $this->authorize("viewAdmin", $workspace);

        return Inertia::render("Admin/Settings", [
            "workspace" => $workspace->load("owner"),
        ]);
    }
}
