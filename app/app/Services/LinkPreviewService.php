<?php

namespace App\Services;

use App\Models\LinkPreview;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LinkPreviewService
{
    /**
     * Extract URLs from text
     */
    public function extractUrls(string $text): array
    {
        $pattern = '/https?:\/\/[^\s<>"{}|\\^`\[\]]+/i';
        preg_match_all($pattern, $text, $matches);

        return array_unique($matches[0] ?? []);
    }

    /**
     * Generate link preview for a URL
     */
    public function generatePreview(Message $message, string $url): ?LinkPreview
    {
        try {
            // Check if preview already exists for this URL
            $existing = LinkPreview::where('message_id', $message->id)
                ->where('url', $url)
                ->first();

            if ($existing) {
                return $existing;
            }

            // Fetch the page
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; LatchBot/1.0; +https://latch.app/bot)',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning("Failed to fetch URL for preview: {$url}", [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $html = $response->body();

            // Extract metadata
            $metadata = $this->extractMetadata($html, $url);

            if (empty($metadata['title']) && empty($metadata['description'])) {
                return null; // Not enough data for a meaningful preview
            }

            // Create link preview
            $preview = LinkPreview::create([
                'message_id' => $message->id,
                'url' => $url,
                'title' => $metadata['title'] ?? null,
                'description' => $metadata['description'] ?? null,
                'image_url' => $metadata['image'] ?? null,
                'site_name' => $metadata['site_name'] ?? null,
            ]);

            return $preview;

        } catch (\Exception $e) {
            Log::error("Error generating link preview: {$url}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract Open Graph and meta tags from HTML
     */
    private function extractMetadata(string $html, string $url): array
    {
        $metadata = [];

        // Extract Open Graph tags
        if (preg_match('/<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $metadata['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match('/<meta\s+property=["\']og:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $metadata['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match('/<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $metadata['image'] = $this->resolveUrl($matches[1], $url);
        }

        if (preg_match('/<meta\s+property=["\']og:site_name["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $metadata['site_name'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        // Fallback to Twitter Card tags
        if (empty($metadata['title']) && preg_match('/<meta\s+name=["\']twitter:title["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $metadata['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        if (empty($metadata['description']) && preg_match('/<meta\s+name=["\']twitter:description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $metadata['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        if (empty($metadata['image']) && preg_match('/<meta\s+name=["\']twitter:image["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $metadata['image'] = $this->resolveUrl($matches[1], $url);
        }

        // Fallback to standard meta tags
        if (empty($metadata['title']) && preg_match('/<title>(.*?)<\/title>/i', $html, $matches)) {
            $metadata['title'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        if (empty($metadata['description']) && preg_match('/<meta\s+name=["\']description["\']\s+content=["\'](.*?)["\']/i', $html, $matches)) {
            $metadata['description'] = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        }

        // Truncate long text
        if (isset($metadata['title'])) {
            $metadata['title'] = mb_substr($metadata['title'], 0, 200);
        }

        if (isset($metadata['description'])) {
            $metadata['description'] = mb_substr($metadata['description'], 0, 500);
        }

        return $metadata;
    }

    /**
     * Resolve relative URLs to absolute
     */
    private function resolveUrl(string $relative, string $base): string
    {
        // Already absolute
        if (parse_url($relative, PHP_URL_SCHEME) !== null) {
            return $relative;
        }

        $baseUrl = parse_url($base);

        // Protocol relative URL
        if (strpos($relative, '//') === 0) {
            return ($baseUrl['scheme'] ?? 'https') . ':' . $relative;
        }

        $scheme = $baseUrl['scheme'] ?? 'https';
        $host = $baseUrl['host'] ?? '';
        $port = isset($baseUrl['port']) ? ':' . $baseUrl['port'] : '';

        // Absolute path
        if (strpos($relative, '/') === 0) {
            return "{$scheme}://{$host}{$port}{$relative}";
        }

        // Relative path
        $path = $baseUrl['path'] ?? '/';
        $path = rtrim(dirname($path), '/');

        return "{$scheme}://{$host}{$port}{$path}/{$relative}";
    }
}
