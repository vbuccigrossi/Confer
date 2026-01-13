<?php

/**
 * Reminder Bot Server
 * 
 * Commands:
 * - /remind me in 30 minutes to check the build
 * - /remind me tomorrow at 9am standup meeting
 * - /remind @user in 1 hour about the meeting
 * - /remind list - Show pending reminders
 * - /remind delete <id> - Delete a reminder
 * - /remind help - Show help
 */

$botToken = getenv("BOT_TOKEN") ?: "your-bot-token-here";
$apiBaseUrl = getenv("API_BASE_URL") ?: "https://nginx/api";
$appBaseUrl = getenv("APP_BASE_URL") ?: "https://nginx";
$port = getenv("PORT") ?: 8003;

echo "⏰ Reminder Bot listening on 0.0.0.0:{$port}\n";
echo "Commands: /remind me|@user|list|delete|help\n\n";

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
        echo "[" . date("Y-m-d H:i:s") . "] /remind command\n";
        handleRemindCommand($data, $botToken, $apiBaseUrl, $appBaseUrl, $client);
    } else {
        sendResponse($client, 200, ["status" => "ok"]);
    }

    fclose($client);
}

function handleRemindCommand($data, $botToken, $apiBaseUrl, $appBaseUrl, $client) {
    $conversationId = $data["conversation_id"];
    $workspaceId = $data["workspace_id"];
    $userId = $data["user_id"];
    $userName = $data["user_name"] ?? "You";
    $args = trim($data["args"] ?? "");

    if (empty($args) || $args === "help") {
        $msg = handleHelp();
    } elseif ($args === "list") {
        $msg = handleList($appBaseUrl, $workspaceId, $userId);
    } elseif (preg_match("/^delete\s+(\d+)$/i", $args, $m)) {
        $msg = handleDelete($appBaseUrl, (int)$m[1], $userId);
    } else {
        $msg = handleCreateReminder($appBaseUrl, $args, $workspaceId, $conversationId, $userId, $userName);
    }

    sendBotMessage($botToken, $apiBaseUrl, $conversationId, $msg);
    sendResponse($client, 200, ["status" => "success"]);
}

function handleHelp() {
    return "⏰ **Reminder Bot Help**\n\n" .
        "**Set a reminder:**\n" .
        "• `/remind me in 30 minutes to check the build`\n" .
        "• `/remind me tomorrow at 9am standup meeting`\n" .
        "• `/remind me at 3pm call John`\n" .
        "• `/remind me next monday review PRs`\n\n" .
        "**Recurring reminders:**\n" .
        "• `/remind me every day at 9am daily standup`\n" .
        "• `/remind me every week on monday weekly review`\n\n" .
        "**Manage reminders:**\n" .
        "• `/remind list` - Show your pending reminders\n" .
        "• `/remind delete <id>` - Delete a reminder\n";
}

function handleList($appBaseUrl, $workspaceId, $userId) {
    $reminders = callInternalApi($appBaseUrl, "/api/internal/reminders/list", [
        "workspace_id" => $workspaceId,
        "user_id" => $userId,
    ]);

    if (!$reminders || empty($reminders["reminders"])) {
        return "📭 You have no pending reminders.";
    }

    $msg = "⏰ **Your Pending Reminders**\n\n";
    foreach ($reminders["reminders"] as $r) {
        $recur = $r["recurrence"] ? " 🔄 {$r["recurrence"]}" : "";
        $msg .= "**#{$r["id"]}** {$r["message"]}\n";
        $msg .= "   📅 {$r["remind_at_human"]}{$recur}\n\n";
    }
    return $msg;
}

function handleDelete($appBaseUrl, $reminderId, $userId) {
    $result = callInternalApi($appBaseUrl, "/api/internal/reminders/delete", [
        "reminder_id" => $reminderId,
        "user_id" => $userId,
    ]);

    if ($result && $result["success"]) {
        return "✅ Reminder #{$reminderId} deleted.";
    }
    return "❌ Could not delete reminder. Make sure the ID is correct and it belongs to you.";
}

function handleCreateReminder($appBaseUrl, $text, $workspaceId, $conversationId, $userId, $userName) {
    // Parse the reminder text
    // Format: "me|@user <time expression> <message>"
    
    $targetUserId = $userId;
    $targetName = "you";
    
    // Check for @mention at start
    if (preg_match("/^@(\w+)\s+/", $text, $m)) {
        $targetName = $m[1];
        $text = trim(substr($text, strlen($m[0])));
        // For now, we set target to self - would need user lookup
    } elseif (preg_match("/^me\s+/i", $text)) {
        $text = trim(substr($text, 3));
    }

    // Parse time and message
    $parsed = parseReminderText($text);
    
    if (!$parsed) {
        return "❌ I could not understand that reminder.\n\n" .
            "Try something like:\n" .
            "• `/remind me in 30 minutes check the build`\n" .
            "• `/remind me tomorrow at 9am standup meeting`\n" .
            "• `/remind me at 5pm go home`";
    }

    $remindAt = $parsed["datetime"];
    $message = $parsed["message"];
    $recurrence = $parsed["recurrence"] ?? null;

    // Create the reminder via internal API
    $result = callInternalApi($appBaseUrl, "/api/internal/reminders/create", [
        "workspace_id" => $workspaceId,
        "conversation_id" => $conversationId,
        "user_id" => $userId,
        "target_user_id" => $targetUserId,
        "message" => $message,
        "remind_at" => $remindAt,
        "recurrence" => $recurrence,
    ]);

    if ($result && $result["success"]) {
        $r = $result["reminder"];
        $timeStr = date("M j, Y \\a\\t g:i A", strtotime($r["remind_at"]));
        $recurStr = $recurrence ? " (repeats {$recurrence})" : "";
        return "✅ Got it! I will remind {$targetName} **{$timeStr}**{$recurStr}\n\n📝 \"{$message}\"";
    }

    return "❌ Failed to create reminder. Please try again.";
}

function parseReminderText($text) {
    $now = time();
    $datetime = null;
    $message = "";
    $recurrence = null;

    // Check for recurrence first
    if (preg_match("/^every\s+(day|daily|week|weekly|weekday|month|monthly)\s*/i", $text, $m)) {
        $recurrence = match(strtolower($m[1])) {
            "day", "daily" => "daily",
            "week", "weekly" => "weekly",
            "weekday", "weekdays" => "weekdays",
            "month", "monthly" => "monthly",
            default => null,
        };
        $text = trim(substr($text, strlen($m[0])));
    }

    // Pattern: "in X minutes/hours/days <message>"
    if (preg_match("/^in\s+(\d+)\s*(min(?:ute)?s?|hours?|days?|weeks?)\s+(?:to\s+)?(.+)$/i", $text, $m)) {
        $amount = (int)$m[1];
        $unit = strtolower($m[2]);
        $message = trim($m[3]);
        
        $seconds = match(true) {
            str_starts_with($unit, "min") => $amount * 60,
            str_starts_with($unit, "hour") => $amount * 3600,
            str_starts_with($unit, "day") => $amount * 86400,
            str_starts_with($unit, "week") => $amount * 604800,
            default => 0,
        };
        $datetime = date("c", $now + $seconds);
    }
    // Pattern: "tomorrow/monday/etc at Xam/pm <message>"
    elseif (preg_match("/^(tomorrow|tonight|monday|tuesday|wednesday|thursday|friday|saturday|sunday|next\s+\w+)(?:\s+at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?)?\s+(?:to\s+)?(.+)$/i", $text, $m)) {
        $dayWord = strtolower($m[1]);
        $hour = isset($m[2]) && $m[2] !== "" ? (int)$m[2] : 9;
        $minute = isset($m[3]) && $m[3] !== "" ? (int)$m[3] : 0;
        $ampm = strtolower($m[4] ?? "");
        $message = trim($m[5]);

        if ($ampm === "pm" && $hour < 12) $hour += 12;
        if ($ampm === "am" && $hour === 12) $hour = 0;

        $baseDate = match(true) {
            $dayWord === "tomorrow" => strtotime("tomorrow"),
            $dayWord === "tonight" => strtotime("today"),
            str_starts_with($dayWord, "next ") => strtotime($dayWord),
            default => strtotime("next " . $dayWord),
        };

        if ($dayWord === "tonight") $hour = max($hour, 18);
        
        $datetime = date("c", mktime($hour, $minute, 0, date("n", $baseDate), date("j", $baseDate), date("Y", $baseDate)));
    }
    // Pattern: "at Xam/pm <message>"
    elseif (preg_match("/^at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\s+(?:to\s+)?(.+)$/i", $text, $m)) {
        $hour = (int)$m[1];
        $minute = isset($m[2]) && $m[2] !== "" ? (int)$m[2] : 0;
        $ampm = strtolower($m[3] ?? "");
        $message = trim($m[4]);

        if ($ampm === "pm" && $hour < 12) $hour += 12;
        if ($ampm === "am" && $hour === 12) $hour = 0;

        $targetTime = mktime($hour, $minute, 0);
        if ($targetTime <= $now) $targetTime += 86400; // Next day
        
        $datetime = date("c", $targetTime);
    }

    if ($datetime && $message) {
        return [
            "datetime" => $datetime,
            "message" => $message,
            "recurrence" => $recurrence,
        ];
    }

    return null;
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