<?php

/**
 * Poll Bot Server
 *
 * Commands:
 * - /poll "Question" "Option 1" "Option 2" "Option 3" - Create a poll
 * - /poll vote <poll_id> <option_number> - Vote on a poll
 * - /poll results <poll_id> - View poll results
 * - /poll close <poll_id> - Close a poll (creator only)
 * - /poll help - Show help
 *
 * Options:
 * - --anonymous - Anonymous voting (voters hidden)
 * - --multi - Allow multiple selections
 * - --closes=<time> - Auto-close at specified time (e.g., --closes=1h, --closes=tomorrow)
 */

$botToken = getenv("BOT_TOKEN") ?: "your-bot-token-here";
$apiBaseUrl = getenv("API_BASE_URL") ?: "https://nginx/api";
$appBaseUrl = getenv("APP_BASE_URL") ?: "https://nginx";
$port = getenv("PORT") ?: 8004;

echo "📊 Poll Bot listening on 0.0.0.0:{$port}\n";
echo "Commands: /poll \"Question\" \"Option1\" \"Option2\" | vote | results | close | help\n\n";

$server = stream_socket_server("tcp://0.0.0.0:{$port}", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
if (!$server) die("Failed to start server: $errstr ($errno)\n");

while (true) {
    $client = @stream_socket_accept($server, -1);
    if (!$client) continue;

    $request = "";
    while ($line = fgets($client)) {
        $request .= $line;
        if ($line === "\r\n") break;
    }

    if (preg_match("/Content-Length: (\d+)/", $request, $matches)) {
        $body = fread($client, (int)$matches[1]);
    } else {
        $body = "";
    }

    $data = json_decode($body, true);

    if ($data && $data["type"] === "slash_command") {
        echo "[" . date("Y-m-d H:i:s") . "] /poll command\n";
        handlePollCommand($data, $botToken, $apiBaseUrl, $appBaseUrl, $client);
    } else {
        sendResponse($client, 200, ["status" => "ok"]);
    }

    fclose($client);
}

function handlePollCommand($data, $botToken, $apiBaseUrl, $appBaseUrl, $client) {
    $conversationId = $data["conversation_id"];
    $workspaceId = $data["workspace_id"];
    $userId = $data["user_id"];
    $userName = $data["user_name"] ?? "Someone";
    $args = trim($data["args"] ?? "");

    if (empty($args) || $args === "help") {
        $msg = handleHelp();
    } elseif (preg_match("/^vote\s+(\d+)\s+(\d+)$/i", $args, $m)) {
        $msg = handleVote($appBaseUrl, (int)$m[1], (int)$m[2], $userId, $userName);
    } elseif (preg_match("/^results\s+(\d+)$/i", $args, $m)) {
        $msg = handleResults($appBaseUrl, (int)$m[1], $userId);
    } elseif (preg_match("/^close\s+(\d+)$/i", $args, $m)) {
        $msg = handleClose($appBaseUrl, (int)$m[1], $userId);
    } else {
        $msg = handleCreatePoll($appBaseUrl, $args, $workspaceId, $conversationId, $userId, $userName);
    }

    sendBotMessage($botToken, $apiBaseUrl, $conversationId, $msg);
    sendResponse($client, 200, ["status" => "success"]);
}

function handleHelp() {
    return "📊 **Poll Bot Help**\n\n" .
        "**Create a poll:**\n" .
        "```\n/poll \"Your question?\" \"Option 1\" \"Option 2\" \"Option 3\"\n```\n\n" .
        "**Options:**\n" .
        "• `--anonymous` - Hide who voted for what\n" .
        "• `--multi` - Allow multiple selections\n" .
        "• `--closes=1h` - Auto-close in 1 hour\n" .
        "• `--closes=tomorrow` - Auto-close tomorrow at 9am\n\n" .
        "**Example:**\n" .
        "```\n/poll --anonymous \"Where for lunch?\" \"Pizza\" \"Sushi\" \"Tacos\"\n```\n\n" .
        "**Vote on a poll:**\n" .
        "• `/poll vote <poll_id> <option_number>` - Vote for an option\n\n" .
        "**Manage polls:**\n" .
        "• `/poll results <poll_id>` - View current results\n" .
        "• `/poll close <poll_id>` - Close voting (creator only)\n";
}

function handleVote($appBaseUrl, $pollId, $optionNumber, $userId, $userName) {
    // Option numbers are 1-indexed for users, but 0-indexed in DB
    $optionIndex = $optionNumber - 1;

    $result = callInternalApi($appBaseUrl, "/api/internal/polls/vote", [
        "poll_id" => $pollId,
        "user_id" => $userId,
        "option_index" => $optionIndex,
    ]);

    if (!$result) {
        return "❌ Could not record vote. Please try again.";
    }

    if (!$result["success"]) {
        return "❌ " . ($result["error"] ?? "Could not record vote.");
    }

    $poll = $result["poll"];
    $action = $result["action"] ?? "recorded";

    if ($action === "removed") {
        return "✅ Your vote for option **{$optionNumber}** has been removed from poll #{$pollId}.\n\n" .
            "📊 **{$poll["question"]}**\n" . formatResults($poll);
    }

    return "✅ Vote {$action} for poll #{$pollId}!\n\n" .
        "📊 **{$poll["question"]}**\n" . formatResults($poll);
}

function handleResults($appBaseUrl, $pollId, $userId) {
    $result = callInternalApi($appBaseUrl, "/api/internal/polls/results", [
        "poll_id" => $pollId,
        "user_id" => $userId,
    ]);

    if (!$result || !$result["success"]) {
        return "❌ Poll #{$pollId} not found.";
    }

    $poll = $result["poll"];
    $closedStr = $poll["is_closed"] ? " (CLOSED)" : "";

    $msg = "📊 **{$poll["question"]}**{$closedStr}\n";
    $msg .= "_Created by {$poll["creator_name"]}_\n\n";
    $msg .= formatResults($poll);

    if ($poll["your_votes"]) {
        $yourVotes = array_map(fn($v) => $v + 1, $poll["your_votes"]);
        $msg .= "\n_Your vote(s): Option " . implode(", ", $yourVotes) . "_";
    }

    if ($poll["closes_at"]) {
        $msg .= "\n_Auto-closes: {$poll["closes_at"]}_";
    }

    return $msg;
}

function handleClose($appBaseUrl, $pollId, $userId) {
    $result = callInternalApi($appBaseUrl, "/api/internal/polls/close", [
        "poll_id" => $pollId,
        "user_id" => $userId,
    ]);

    if (!$result || !$result["success"]) {
        $error = $result["error"] ?? "Could not close poll.";
        return "❌ {$error}";
    }

    $poll = $result["poll"];
    return "🔒 Poll #{$pollId} is now closed.\n\n" .
        "📊 **Final Results: {$poll["question"]}**\n" . formatResults($poll);
}

function handleCreatePoll($appBaseUrl, $text, $workspaceId, $conversationId, $userId, $userName) {
    // Parse options from text
    $isAnonymous = false;
    $isMultiSelect = false;
    $closesAt = null;

    // Check for flags
    if (preg_match("/--anonymous/i", $text)) {
        $isAnonymous = true;
        $text = preg_replace("/--anonymous\s*/i", "", $text);
    }
    if (preg_match("/--multi/i", $text)) {
        $isMultiSelect = true;
        $text = preg_replace("/--multi\s*/i", "", $text);
    }
    if (preg_match("/--closes=(\S+)/i", $text, $m)) {
        $closesAt = parseCloseTime($m[1]);
        $text = preg_replace("/--closes=\S+\s*/i", "", $text);
    }

    $text = trim($text);

    // Parse quoted strings for question and options
    preg_match_all('/"([^"]+)"/', $text, $matches);

    if (count($matches[1]) < 3) {
        return "❌ Please provide a question and at least 2 options.\n\n" .
            "**Example:**\n" .
            "```\n/poll \"Where for lunch?\" \"Pizza\" \"Sushi\" \"Tacos\"\n```";
    }

    $question = $matches[1][0];
    $options = array_slice($matches[1], 1);

    if (count($options) > 10) {
        return "❌ Maximum 10 options allowed.";
    }

    // Create poll via internal API
    $result = callInternalApi($appBaseUrl, "/api/internal/polls/create", [
        "workspace_id" => $workspaceId,
        "conversation_id" => $conversationId,
        "user_id" => $userId,
        "question" => $question,
        "options" => $options,
        "is_anonymous" => $isAnonymous,
        "is_multi_select" => $isMultiSelect,
        "closes_at" => $closesAt,
    ]);

    if (!$result || !$result["success"]) {
        return "❌ Failed to create poll. Please try again.";
    }

    $poll = $result["poll"];
    $pollId = $poll["id"];

    $flags = [];
    if ($isAnonymous) $flags[] = "Anonymous";
    if ($isMultiSelect) $flags[] = "Multi-select";
    $flagStr = $flags ? " (" . implode(", ", $flags) . ")" : "";

    $msg = "📊 **New Poll #{$pollId}**{$flagStr}\n";
    $msg .= "_Created by {$userName}_\n\n";
    $msg .= "**{$question}**\n\n";

    foreach ($options as $i => $opt) {
        $num = $i + 1;
        $msg .= "  {$num}. {$opt}\n";
    }

    $msg .= "\n**To vote:** `/poll vote {$pollId} <option_number>`\n";

    if ($closesAt) {
        $closeTime = date("M j, Y \\a\\t g:i A", strtotime($closesAt));
        $msg .= "_Closes: {$closeTime}_\n";
    }

    return $msg;
}

function parseCloseTime($timeStr) {
    $now = time();

    // Pattern: 1h, 2h, 30m, etc.
    if (preg_match("/^(\d+)(h|m|d)$/i", $timeStr, $m)) {
        $amount = (int)$m[1];
        $unit = strtolower($m[2]);
        $seconds = match($unit) {
            "m" => $amount * 60,
            "h" => $amount * 3600,
            "d" => $amount * 86400,
            default => 3600,
        };
        return date("c", $now + $seconds);
    }

    // Pattern: tomorrow, monday, etc.
    $parsed = strtotime($timeStr);
    if ($parsed && $parsed > $now) {
        return date("c", $parsed);
    }

    return null;
}

function formatResults($poll) {
    $results = $poll["results"];
    $totalVotes = $poll["total_votes"];
    $isAnonymous = $poll["is_anonymous"];

    $msg = "";
    foreach ($results as $index => $data) {
        $num = $index + 1;
        $votes = $data["votes"];
        $pct = $totalVotes > 0 ? round(($votes / $totalVotes) * 100) : 0;
        $bar = str_repeat("█", (int)($pct / 10)) . str_repeat("░", 10 - (int)($pct / 10));

        $msg .= "  {$num}. {$data["option"]}\n";
        $msg .= "     {$bar} {$pct}% ({$votes} vote" . ($votes !== 1 ? "s" : "") . ")\n";

        // Show voters for non-anonymous polls
        if (!$isAnonymous && !empty($data["voters"])) {
            $voterList = implode(", ", array_slice($data["voters"], 0, 5));
            if (count($data["voters"]) > 5) {
                $voterList .= " +" . (count($data["voters"]) - 5) . " more";
            }
            $msg .= "     _Voters: {$voterList}_\n";
        }
    }

    $msg .= "\n_Total: {$totalVotes} vote" . ($totalVotes !== 1 ? "s" : "") . "_";
    return $msg;
}

function callInternalApi($baseUrl, $endpoint, $data) {
    $url = rtrim($baseUrl, "/") . $endpoint;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
    }

    echo "    Internal API error: HTTP {$httpCode}\n";
    return null;
}

function sendBotMessage($token, $apiBaseUrl, $conversationId, $text) {
    $ch = curl_init("{$apiBaseUrl}/bot/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(["conversation_id" => $conversationId, "text" => $text]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Authorization: Bearer " . $token],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function sendResponse($client, $code, $data) {
    $body = json_encode($data);
    fwrite($client, "HTTP/1.1 {$code} OK\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body);
}
