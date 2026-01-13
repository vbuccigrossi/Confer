<?php

/**
 * GitLab Bot Server - Full Featured
 * 
 * Slash Commands:
 * - /gitlab projects - List accessible projects
 * - /gitlab issues [project] - List open issues
 * - /gitlab mrs [project] - List open merge requests  
 * - /gitlab pipeline [project] - Show latest pipeline status
 * - /gitlab commits [project] - Show recent commits
 * - /gitlab webhook - Show webhook URL for GitLab configuration
 * - /gitlab help - Show available commands
 */

$botToken = getenv("BOT_TOKEN") ?: "your-bot-token-here";
$apiBaseUrl = getenv("API_BASE_URL") ?: "https://nginx/api";
$port = getenv("PORT") ?: 8002;

$address = "0.0.0.0";

echo "🦊 GitLab Bot listening on {$address}:{$port}\n";
echo "Commands: /gitlab projects|issues|mrs|pipeline|commits|webhook|help\n\n";

$server = stream_socket_server(
    "tcp://{$address}:{$port}",
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
);

if (!$server) {
    die("Failed to start server: $errstr ($errno)\n");
}

while (true) {
    $client = @stream_socket_accept($server, -1);
    if (!$client) continue;

    $request = "";
    while ($line = fgets($client)) {
        $request .= $line;
        if ($line === "\r\n") break;
    }

    if (preg_match("/Content-Length: (\d+)/", $request, $matches)) {
        $contentLength = (int)$matches[1];
        $body = fread($client, $contentLength);
    } else {
        $body = "";
    }

    $data = json_decode($body, true);

    if ($data && $data["type"] === "slash_command") {
        echo "[" . date("Y-m-d H:i:s") . "] Received /gitlab command\n";
        handleGitLabCommand($data, $botToken, $apiBaseUrl, $client);
    } else {
        sendResponse($client, 200, ["status" => "ok"]);
    }

    fclose($client);
}

function handleGitLabCommand($data, $botToken, $apiBaseUrl, $client) {
    $conversationId = $data["conversation_id"];
    $workspaceId = $data["workspace_id"] ?? null;
    $config = $data["config"] ?? [];
    $args = trim($data["args"] ?? "");

    $parts = preg_split("/\s+/", $args, 2);
    $subcommand = strtolower($parts[0] ?? "help");
    $subargs = $parts[1] ?? "";

    $gitlabUrl = rtrim($config["gitlab_url"] ?? "", "/");
    $apiToken = $config["api_token"] ?? "";
    $defaultProject = $config["default_project"] ?? "";

    echo "    Subcommand: {$subcommand}\n";

    // Commands that work without GitLab config
    if ($subcommand === "help") {
        $message = handleHelp($defaultProject);
        sendBotMessage($botToken, $apiBaseUrl, $conversationId, $message);
        sendResponse($client, 200, ["status" => "success"]);
        return;
    }

    if ($subcommand === "webhook") {
        $message = handleWebhookInfo($workspaceId, $config);
        sendBotMessage($botToken, $apiBaseUrl, $conversationId, $message);
        sendResponse($client, 200, ["status" => "success"]);
        return;
    }

    // Check GitLab config for other commands
    if (empty($gitlabUrl) || empty($apiToken)) {
        $message = "⚠️ **GitLab not configured**\n\n";
        $message .= "Please ask a workspace admin to configure the GitLab bot with:\n";
        $message .= "• GitLab URL\n";
        $message .= "• API Token (with `api` scope)\n\n";
        $message .= "Go to **Admin → Bots → Configure** on GitLab Bot.";
        sendBotMessage($botToken, $apiBaseUrl, $conversationId, $message);
        sendResponse($client, 200, ["status" => "success"]);
        return;
    }

    $message = match($subcommand) {
        "projects" => handleProjects($gitlabUrl, $apiToken),
        "issues" => handleIssues($gitlabUrl, $apiToken, $subargs ?: $defaultProject),
        "mrs", "mr" => handleMergeRequests($gitlabUrl, $apiToken, $subargs ?: $defaultProject),
        "pipeline", "pipelines" => handlePipeline($gitlabUrl, $apiToken, $subargs ?: $defaultProject),
        "commits", "commit" => handleCommits($gitlabUrl, $apiToken, $subargs ?: $defaultProject),
        "search" => handleSearch($gitlabUrl, $apiToken, $subargs),
        default => "❌ Unknown command: `{$subcommand}`\n\nUse `/gitlab help` for available commands.",
    };

    sendBotMessage($botToken, $apiBaseUrl, $conversationId, $message);
    sendResponse($client, 200, ["status" => "success"]);
}

function handleHelp($defaultProject) {
    $msg = "🦊 **GitLab Bot Commands**\n\n";
    $msg .= "**Slash Commands:**\n";
    $msg .= "• `/gitlab projects` - List your accessible projects\n";
    $msg .= "• `/gitlab issues [project]` - List open issues\n";
    $msg .= "• `/gitlab mrs [project]` - List open merge requests\n";
    $msg .= "• `/gitlab pipeline [project]` - Show recent pipelines\n";
    $msg .= "• `/gitlab commits [project]` - Show recent commits\n";
    $msg .= "• `/gitlab search <query>` - Search across projects\n";
    $msg .= "• `/gitlab webhook` - Show webhook setup info\n";
    $msg .= "• `/gitlab help` - Show this help\n\n";
    
    $msg .= "**Webhook Notifications:**\n";
    $msg .= "Configure GitLab to send webhooks for real-time notifications:\n";
    $msg .= "• Push events (new commits)\n";
    $msg .= "• Merge request events\n";
    $msg .= "• Pipeline status changes\n";
    $msg .= "• Issue events\n";
    $msg .= "• Tag events\n\n";
    
    if ($defaultProject) {
        $msg .= "**Default Project:** `{$defaultProject}`\n";
    }
    
    return $msg;
}

function handleWebhookInfo($workspaceId, $config) {
    // Get installation ID from somewhere - we need to look this up
    // For now, provide generic instructions
    
    $msg = "🔗 **GitLab Webhook Setup**\n\n";
    $msg .= "To receive real-time notifications from GitLab:\n\n";
    $msg .= "**1. Get your webhook URL:**\n";
    $msg .= "Go to **Admin → Bots → GitLab Bot** and copy the webhook URL shown there.\n\n";
    $msg .= "**2. In GitLab, go to:**\n";
    $msg .= "`Settings → Webhooks` in your project or group\n\n";
    $msg .= "**3. Configure the webhook:**\n";
    $msg .= "• **URL:** Your Latch webhook URL\n";
    $msg .= "• **Secret token:** (optional) Set in bot config for security\n";
    $msg .= "• **Trigger:** Select events you want (Push, MR, Pipeline, etc.)\n";
    $msg .= "• **SSL verification:** Enable if using HTTPS\n\n";
    $msg .= "**4. Enable notifications in bot config:**\n";
    $msg .= "Make sure the corresponding notification toggles are enabled.\n";
    
    if (!empty($config["webhook_secret"])) {
        $msg .= "\n✅ Webhook secret is configured";
    } else {
        $msg .= "\n⚠️ No webhook secret configured (recommended for security)";
    }
    
    return $msg;
}

function handleProjects($gitlabUrl, $apiToken) {
    $projects = gitlabApi($gitlabUrl, $apiToken, "projects", [
        "membership" => "true",
        "per_page" => 10,
        "order_by" => "last_activity_at"
    ]);
    
    if ($projects === null) {
        return "❌ Failed to fetch projects. Check your GitLab configuration.";
    }
    
    if (empty($projects)) {
        return "📂 No projects found. You may not have access to any projects.";
    }
    
    $msg = "📂 **Your GitLab Projects** (most recently active)\n\n";
    foreach ($projects as $project) {
        $v = $project["visibility"] === "private" ? "🔒" : "🌐";
        $msg .= "{$v} **{$project["path_with_namespace"]}**\n";
        if (!empty($project["description"])) {
            $desc = strlen($project["description"]) > 60 
                ? substr($project["description"], 0, 57) . "..." 
                : $project["description"];
            $msg .= "   _{$desc}_\n";
        }
        $msg .= "   ⭐ {$project["star_count"]} | 🍴 {$project["forks_count"]}\n\n";
    }
    return $msg;
}

function handleIssues($gitlabUrl, $apiToken, $projectPath) {
    if (empty($projectPath)) {
        return "❌ Please specify a project: `/gitlab issues group/project`\n\nOr set a default project in bot configuration.";
    }
    
    $issues = gitlabApi($gitlabUrl, $apiToken, "projects/" . urlencode($projectPath) . "/issues", [
        "state" => "opened",
        "per_page" => 10,
        "order_by" => "updated_at"
    ]);
    
    if ($issues === null) {
        return "❌ Failed to fetch issues.\n\nCheck that project `{$projectPath}` exists and you have access.";
    }
    
    if (empty($issues)) {
        return "✅ No open issues in **{$projectPath}**\n\n🎉 Great job keeping the issue tracker clean!";
    }
    
    $msg = "📋 **Open Issues in {$projectPath}**\n\n";
    foreach ($issues as $issue) {
        $assignee = $issue["assignee"]["name"] ?? "Unassigned";
        $labels = "";
        if (!empty($issue["labels"])) {
            $labelList = array_slice($issue["labels"], 0, 2);
            $labels = " `" . implode("` `", $labelList) . "`";
        }
        $msg .= "**#{$issue["iid"]}** {$issue["title"]}{$labels}\n";
        $msg .= "   👤 {$assignee} | 💬 {$issue["user_notes_count"]} | [View]({$issue["web_url"]})\n\n";
    }
    return $msg;
}

function handleMergeRequests($gitlabUrl, $apiToken, $projectPath) {
    if (empty($projectPath)) {
        return "❌ Please specify a project: `/gitlab mrs group/project`";
    }
    
    $mrs = gitlabApi($gitlabUrl, $apiToken, "projects/" . urlencode($projectPath) . "/merge_requests", [
        "state" => "opened",
        "per_page" => 10,
        "order_by" => "updated_at"
    ]);
    
    if ($mrs === null) {
        return "❌ Failed to fetch merge requests for `{$projectPath}`";
    }
    
    if (empty($mrs)) {
        return "✅ No open merge requests in **{$projectPath}**";
    }
    
    $msg = "🔀 **Open Merge Requests in {$projectPath}**\n\n";
    foreach ($mrs as $mr) {
        $status = "⏳";
        if ($mr["draft"] ?? false) {
            $status = "📝";
        } elseif ($mr["merge_status"] === "can_be_merged") {
            $status = "✅";
        } elseif ($mr["merge_status"] === "cannot_be_merged") {
            $status = "⚠️";
        }
        
        $author = $mr["author"]["name"] ?? "Unknown";
        $approvals = "";
        if (isset($mr["approvals_required"]) && $mr["approvals_required"] > 0) {
            $approved = $mr["approved"] ?? false;
            $approvals = $approved ? " ✓" : " (needs approval)";
        }
        
        $msg .= "{$status} **!{$mr["iid"]}** {$mr["title"]}{$approvals}\n";
        $msg .= "   `{$mr["source_branch"]}` → `{$mr["target_branch"]}`\n";
        $msg .= "   👤 {$author} | [View]({$mr["web_url"]})\n\n";
    }
    return $msg;
}

function handlePipeline($gitlabUrl, $apiToken, $projectPath) {
    if (empty($projectPath)) {
        return "❌ Please specify a project: `/gitlab pipeline group/project`";
    }
    
    $pipelines = gitlabApi($gitlabUrl, $apiToken, "projects/" . urlencode($projectPath) . "/pipelines", [
        "per_page" => 5,
        "order_by" => "updated_at"
    ]);
    
    if ($pipelines === null) {
        return "❌ Failed to fetch pipelines for `{$projectPath}`";
    }
    
    if (empty($pipelines)) {
        return "📭 No pipelines found in **{$projectPath}**\n\nPipelines run when you have CI/CD configured.";
    }
    
    $msg = "🚀 **Recent Pipelines in {$projectPath}**\n\n";
    foreach ($pipelines as $p) {
        $status = match($p["status"]) {
            "success" => "✅ Passed",
            "failed" => "❌ Failed",
            "running" => "🔄 Running",
            "pending" => "⏳ Pending",
            "canceled" => "🚫 Canceled",
            "skipped" => "⏭️ Skipped",
            "manual" => "👆 Manual",
            default => "❓ " . $p["status"],
        };
        
        $duration = "";
        if (isset($p["duration"]) && $p["duration"] > 0) {
            $mins = floor($p["duration"] / 60);
            $secs = $p["duration"] % 60;
            $duration = " ({$mins}m {$secs}s)";
        }
        
        $msg .= "**#{$p["id"]}** {$status}{$duration}\n";
        $msg .= "   📌 `{$p["ref"]}` | [View]({$p["web_url"]})\n\n";
    }
    return $msg;
}

function handleCommits($gitlabUrl, $apiToken, $projectPath) {
    if (empty($projectPath)) {
        return "❌ Please specify a project: `/gitlab commits group/project`";
    }
    
    $commits = gitlabApi($gitlabUrl, $apiToken, "projects/" . urlencode($projectPath) . "/repository/commits", [
        "per_page" => 5
    ]);
    
    if ($commits === null) {
        return "❌ Failed to fetch commits for `{$projectPath}`";
    }
    
    if (empty($commits)) {
        return "📭 No commits found in **{$projectPath}**";
    }
    
    $msg = "📝 **Recent Commits in {$projectPath}**\n\n";
    foreach ($commits as $c) {
        $sha = substr($c["id"], 0, 8);
        $title = strlen($c["title"]) > 50 ? substr($c["title"], 0, 47) . "..." : $c["title"];
        $date = date("M j, H:i", strtotime($c["created_at"]));
        
        $msg .= "**`{$sha}`** {$title}\n";
        $msg .= "   👤 {$c["author_name"]} | 🕐 {$date}\n\n";
    }
    return $msg;
}

function handleSearch($gitlabUrl, $apiToken, $query) {
    if (empty($query)) {
        return "❌ Please provide a search query: `/gitlab search <query>`";
    }
    
    $results = gitlabApi($gitlabUrl, $apiToken, "search", [
        "scope" => "projects",
        "search" => $query,
        "per_page" => 5
    ]);
    
    if ($results === null || empty($results)) {
        return "🔍 No projects found matching `{$query}`";
    }
    
    $msg = "🔍 **Search Results for \"{$query}\"**\n\n";
    foreach ($results as $project) {
        $v = $project["visibility"] === "private" ? "🔒" : "🌐";
        $msg .= "{$v} **{$project["path_with_namespace"]}**\n";
        if (!empty($project["description"])) {
            $desc = strlen($project["description"]) > 60 
                ? substr($project["description"], 0, 57) . "..." 
                : $project["description"];
            $msg .= "   _{$desc}_\n";
        }
        $msg .= "\n";
    }
    return $msg;
}

function gitlabApi($gitlabUrl, $apiToken, $endpoint, $params = []) {
    $url = "{$gitlabUrl}/api/v4/{$endpoint}";
    if (!empty($params)) {
        $url .= "?" . http_build_query($params);
    }
    
    echo "    API: {$endpoint}\n";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            "PRIVATE-TOKEN: {$apiToken}",
            "Accept: application/json"
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => "LatchGitLabBot/1.0",
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        echo "    Error: {$error}\n";
        return null;
    }
    
    if ($httpCode !== 200) {
        echo "    HTTP {$httpCode}\n";
        return null;
    }
    
    return json_decode($response, true);
}

function sendBotMessage($token, $apiBaseUrl, $conversationId, $text) {
    $ch = curl_init("{$apiBaseUrl}/bot/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            "conversation_id" => $conversationId,
            "text" => $text
        ]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . $token
        ],
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "    Posted message: HTTP {$httpCode}\n";
}

function sendResponse($client, $code, $data) {
    $body = json_encode($data);
    fwrite($client, "HTTP/1.1 {$code} OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
}