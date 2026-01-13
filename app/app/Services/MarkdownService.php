<?php

namespace App\Services;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Spatie\CommonMarkHighlighter\FencedCodeRenderer;
use Spatie\CommonMarkHighlighter\IndentedCodeRenderer;

class MarkdownService
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        // Configure environment with strict settings
        $config = [
            'html_input' => 'strip', // Strip all HTML input
            'allow_unsafe_links' => false, // Block javascript: and data: URIs
            'max_nesting_level' => 10, // Prevent deep nesting attacks
            'commonmark' => [
                'enable_em' => true,
                'enable_strong' => true,
                'use_asterisk' => true,
                'use_underscore' => true,
            ],
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        // Add syntax highlighting for code blocks
        $environment->addRenderer(
            \League\CommonMark\Extension\CommonMark\Node\Block\FencedCode::class,
            new FencedCodeRenderer(['html', 'php', 'js', 'python', 'css', 'json', 'bash', 'sql', 'yaml'])
        );
        $environment->addRenderer(
            \League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode::class,
            new IndentedCodeRenderer()
        );

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * Parse Markdown to sanitized HTML
     */
    public function parse(string $markdown): string
    {
        // First convert markdown to HTML
        $html = $this->converter->convert($markdown)->getContent();

        // Then convert @mentions to styled spans (after markdown parsing)
        $html = $this->convertMentionsToHtml($html);

        // Additional sanitization layer
        return $this->sanitize($html);
    }

    /**
     * Convert @username mentions and #channel links to styled spans
     */
    private function convertMentionsToHtml(string $html): string
    {
        // Convert @username to <span class="mention">@username</span>
        $html = preg_replace(
            '/@([a-zA-Z0-9_-]+)/',
            '<span class="mention" data-user="$1">@$1</span>',
            $html
        );

        // Convert #channel-name to <span class="channel-link">#channel-name</span>
        $html = preg_replace(
            '/#([a-zA-Z0-9_-]+)/',
            '<span class="channel-link" data-channel="$1">#$1</span>',
            $html
        );

        return $html;
    }

    /**
     * Sanitize HTML with strict allowlist
     */
    private function sanitize(string $html): string
    {
        // Strip all HTML tags except our allowlist
        $allowed_tags = '<p><br><strong><em><code><pre><a><ul><ol><li><blockquote><h1><h2><h3><h4><h5><h6><hr><del><ins><sup><sub><table><thead><tbody><tr><th><td><span>';

        $html = strip_tags($html, $allowed_tags);

        // Add rel="noopener noreferrer" to all links for security
        $html = preg_replace(
            '/<a\s+([^>]*?)href=(["\'])(.*?)\2([^>]*?)>/i',
            '<a $1href=$2$3$2 rel="noopener noreferrer"$4>',
            $html
        );

        // Remove any remaining inline styles (but preserve mention spans)
        $html = preg_replace('/\s*style\s*=\s*["\'][^"\']*["\']/i', '', $html);

        // Remove any remaining onclick/onerror/etc handlers
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);

        // Remove any data-* attributes EXCEPT data-user (for mentions) and data-channel (for channel links)
        $html = preg_replace('/\s*data-(?!(?:user|channel)\b)\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);

        return trim($html);
    }

    /**
     * Extract mentions from Markdown text
     * Returns array with 'users' and 'channels' keys
     */
    public function extractMentions(string $markdown): array
    {
        $mentions = [
            'users' => [],
            'channels' => [],
        ];

        // Extract @username mentions
        preg_match_all('/@([a-zA-Z0-9_-]+)/', $markdown, $userMatches);
        if (!empty($userMatches[1])) {
            $mentions['users'] = array_unique($userMatches[1]);
        }

        // Extract #channel mentions
        preg_match_all('/#([a-zA-Z0-9_-]+)/', $markdown, $channelMatches);
        if (!empty($channelMatches[1])) {
            $mentions['channels'] = array_unique($channelMatches[1]);
        }

        return $mentions;
    }
}
