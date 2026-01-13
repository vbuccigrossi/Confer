<?php

/**
 * Enhanced News Bot Server with Real RSS Feeds
 * 
 * Features:
 * - /news - Get top headlines using configured default category
 * - /news tech - Get technology news
 * - /news science - Get science news
 * - /news business - Get business news
 * - /news world - Get world news
 * - /news music - Get music news
 * 
 * Configuration (from admin panel):
 * - default_category: Default news category when none specified
 * - max_headlines: Maximum number of headlines to show (1-10)
 * - show_descriptions: Whether to include article descriptions
 */

// Configuration
$botToken = getenv('BOT_TOKEN') ?: 'your-bot-token-here';
$apiBaseUrl = getenv('API_BASE_URL') ?: 'https://nginx/api';
$port = getenv('PORT') ?: 8001;

// Simple HTTP server
$address = '0.0.0.0';
$context = stream_context_create();

echo "🤖 Enhanced News Bot listening on {$address}:{$port}\n";
echo "Waiting for slash command webhooks...\n";
echo "Supported categories: general, tech, science, business, world, music\n";
echo "Using real RSS feeds from NY Times and Rolling Stone\n";
echo "Respects workspace configuration for defaults\n\n";

// Start HTTP server
$server = stream_socket_server(
    "tcp://{$address}:{$port}",
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context
);

if (!$server) {
    die("Failed to start server: $errstr ($errno)\n");
}

while (true) {
    $client = @stream_socket_accept($server, -1);
    if (!$client) continue;

    $request = '';
    while ($line = fgets($client)) {
        $request .= $line;
        if ($line === "\r\n") break;
    }

    // Get content length
    if (preg_match('/Content-Length: (\d+)/', $request, $matches)) {
        $contentLength = (int)$matches[1];
        $body = fread($client, $contentLength);
    } else {
        $body = '';
    }

    // Parse JSON body
    $data = json_decode($body, true);

    if ($data && $data['type'] === 'slash_command') {
        echo "[" . date('Y-m-d H:i:s') . "] Received /news command\n";
        if (!empty($data['config'])) {
            echo "    Config: " . json_encode($data['config']) . "\n";
        }
        handleNewsCommand($data, $botToken, $apiBaseUrl, $client);
    } else {
        sendResponse($client, 200, ['status' => 'ok']);
    }

    fclose($client);
}

fclose($server);

function handleNewsCommand($data, $botToken, $apiBaseUrl, $client) {
    $conversationId = $data['conversation_id'];
    $config = $data['config'] ?? [];

    // Get category from args, or fall back to config default, or 'general'
    $category = trim($data['args'] ?? '');
    if (empty($category)) {
        // Use configured default category, mapping config values to RSS categories
        $defaultCategory = $config['default_category'] ?? 'general';
        // Map config values (e.g., 'technology') to RSS category names (e.g., 'tech')
        $categoryMap = [
            'technology' => 'tech',
            'general' => 'general',
            'science' => 'science',
            'business' => 'business',
            'health' => 'science',
            'sports' => 'general',
            'entertainment' => 'music',
        ];
        $category = $categoryMap[$defaultCategory] ?? $defaultCategory;
        echo "    Using configured default category: {$defaultCategory} -> {$category}\n";
    }

    // Get max headlines from config (default 5)
    $maxHeadlines = (int)($config['max_headlines'] ?? 5);
    $maxHeadlines = max(1, min(10, $maxHeadlines));

    // Get show descriptions setting (default true)
    $showDescriptions = $config['show_descriptions'] ?? true;

    echo "📰 Fetching {$category} news (max: {$maxHeadlines}, descriptions: " . ($showDescriptions ? 'yes' : 'no') . ")...\n";

    // Fetch news for the specified category
    $news = fetchNews($category, $maxHeadlines);

    if (empty($news)) {
        $message = "❌ Sorry, no news found for category: **{$category}**\n\n";
        $message .= "Available categories: `general`, `tech`, `science`, `business`, `world`, `music`";
    } else {
        $categoryEmoji = getCategoryEmoji($category);
        $message = "{$categoryEmoji} **" . ucfirst($category) . " News Headlines**\n\n";

        foreach ($news as $i => $article) {
            $message .= ($i + 1) . ". **{$article['title']}**\n";
            if ($showDescriptions && !empty($article['description'])) {
                $message .= "   {$article['description']}\n";
            }
            $message .= "   _{$article['source']}_ • {$article['time']}\n";
            $message .= "   [Read more]({$article['url']})\n\n";
        }

        $message .= "_Try: `/news tech`, `/news science`, `/news business`, `/news world`, or `/news music`_";
    }

    $result = sendBotMessage($botToken, $apiBaseUrl, $conversationId, $message);

    if ($result) {
        echo "✅ {$category} news posted successfully\n\n";
        sendResponse($client, 200, ['status' => 'success']);
    } else {
        echo "❌ Failed to post news\n\n";
        sendResponse($client, 500, ['status' => 'error']);
    }
}

function getCategoryEmoji($category) {
    $emojis = [
        'general' => '📰',
        'tech' => '💻',
        'science' => '🔬',
        'business' => '💼',
        'world' => '🌍',
        'music' => '🎵',
    ];
    return $emojis[$category] ?? '📰';
}

function fetchNews($category = 'general', $limit = 5) {
    $rssFeedUrls = [
        'general' => 'https://rss.nytimes.com/services/xml/rss/nyt/HomePage.xml',
        'tech' => 'https://rss.nytimes.com/services/xml/rss/nyt/Technology.xml',
        'science' => 'https://rss.nytimes.com/services/xml/rss/nyt/Science.xml',
        'business' => 'https://rss.nytimes.com/services/xml/rss/nyt/Business.xml',
        'world' => 'https://rss.nytimes.com/services/xml/rss/nyt/World.xml',
        'music' => 'https://www.rollingstone.com/music/feed/',
    ];

    $category = strtolower($category);
    if (!isset($rssFeedUrls[$category])) {
        echo "⚠️  Unknown category: {$category}\n";
        return [];
    }

    $feedUrl = $rssFeedUrls[$category];
    $ch = curl_init($feedUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'NewsBot/1.0',
    ]);

    $xmlContent = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200 || !$xmlContent) {
        echo "⚠️  Failed to fetch RSS feed (HTTP {$httpCode})\n";
        if ($error) echo "    Error: {$error}\n";
        return [];
    }

    $xml = @simplexml_load_string($xmlContent);
    if ($xml === false) {
        echo "⚠️  Failed to parse RSS XML\n";
        return [];
    }

    $articles = [];
    $count = 0;

    foreach ($xml->channel->item as $item) {
        if ($count >= $limit) break;

        $pubDate = (string)$item->pubDate;
        $timestamp = strtotime($pubDate);
        $timeAgo = getTimeAgo($timestamp);
        $source = (string)$xml->channel->title;
        $title = (string)$item->title;
        $url = (string)$item->link;
        $description = strip_tags((string)$item->description);
        if (strlen($description) > 150) {
            $description = substr($description, 0, 147) . '...';
        }

        if (empty($title) || empty($url)) continue;

        $articles[] = [
            'title' => $title,
            'description' => $description,
            'source' => $source,
            'time' => $timeAgo,
            'url' => $url,
        ];
        $count++;
    }

    echo "    Found {$count} articles\n";
    return $articles;
}

function getTimeAgo($timestamp) {
    $now = time();
    $diff = $now - $timestamp;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    return date('M j, Y', $timestamp);
}

function sendBotMessage($token, $apiBaseUrl, $conversationId, $text) {
    $ch = curl_init("{$apiBaseUrl}/bot/messages");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'conversation_id' => $conversationId,
            'text' => $text,
        ]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode >= 200 && $httpCode < 300;
}

function sendResponse($client, $code, $data) {
    $body = json_encode($data);
    $response = "HTTP/1.1 {$code} OK\r\n";
    $response .= "Content-Type: application/json\r\n";
    $response .= "Content-Length: " . strlen($body) . "\r\n";
    $response .= "Connection: close\r\n\r\n";
    $response .= $body;
    fwrite($client, $response);
}
