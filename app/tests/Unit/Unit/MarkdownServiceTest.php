<?php

namespace Tests\Unit\Unit;

use App\Services\MarkdownService;
use Tests\TestCase;

class MarkdownServiceTest extends TestCase
{
    private MarkdownService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MarkdownService();
    }

    public function test_basic_markdown_renders_correctly(): void
    {
        $markdown = 'Hello **world**!';
        $html = $this->service->parse($markdown);

        $this->assertStringContainsString('<strong>world</strong>', $html);
    }

    public function test_script_tags_are_stripped(): void
    {
        $markdown = 'Hello <script>alert("XSS")</script> world';
        $html = $this->service->parse($markdown);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('</script>', $html);
    }

    public function test_inline_styles_are_removed(): void
    {
        $markdown = '<p style="color: red;">Styled text</p>';
        $html = $this->service->parse($markdown);

        $this->assertStringNotContainsString('style=', $html);
    }

    public function test_onclick_handlers_are_removed(): void
    {
        $markdown = '<a onclick="alert(\'XSS\')">Click me</a>';
        $html = $this->service->parse($markdown);

        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_links_get_rel_noopener(): void
    {
        $markdown = '[Link](https://example.com)';
        $html = $this->service->parse($markdown);

        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_code_blocks_render_correctly(): void
    {
        $markdown = "```php\necho 'hello';\n```";
        $html = $this->service->parse($markdown);

        $this->assertStringContainsString('<pre>', $html);
        $this->assertStringContainsString('code', $html); // Check for code element (may have class attribute)
        $this->assertStringContainsString('echo', $html);
    }

    public function test_inline_code_renders_correctly(): void
    {
        $markdown = 'Use `console.log()` for debugging';
        $html = $this->service->parse($markdown);

        $this->assertStringContainsString('<code>console.log()</code>', $html);
    }

    public function test_nested_malicious_tags_are_stripped(): void
    {
        $markdown = '<div><script>alert("XSS")</script></div>';
        $html = $this->service->parse($markdown);

        $this->assertStringNotContainsString('<div>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_iframe_tags_are_stripped(): void
    {
        $markdown = '<iframe src="evil.com"></iframe>';
        $html = $this->service->parse($markdown);

        $this->assertStringNotContainsString('<iframe>', $html);
        $this->assertStringNotContainsString('evil.com', $html);
    }

    public function test_extract_mentions_finds_users(): void
    {
        $markdown = 'Hello @john and @jane!';
        $mentions = $this->service->extractMentions($markdown);

        $this->assertContains('john', $mentions['users']);
        $this->assertContains('jane', $mentions['users']);
    }

    public function test_extract_mentions_finds_channels(): void
    {
        $markdown = 'Check out #general and #random';
        $mentions = $this->service->extractMentions($markdown);

        $this->assertContains('general', $mentions['channels']);
        $this->assertContains('random', $mentions['channels']);
    }

    public function test_extract_mentions_removes_duplicates(): void
    {
        $markdown = 'Hello @john and @john again!';
        $mentions = $this->service->extractMentions($markdown);

        $this->assertCount(1, $mentions['users']);
        $this->assertContains('john', $mentions['users']);
    }

    public function test_javascript_protocol_is_blocked(): void
    {
        $markdown = '[Click](javascript:alert("XSS"))';
        $html = $this->service->parse($markdown);

        // CommonMark should strip javascript: URIs due to allow_unsafe_links = false
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_data_protocol_is_blocked(): void
    {
        $markdown = '[Click](data:text/html,<script>alert("XSS")</script>)';
        $html = $this->service->parse($markdown);

        // CommonMark should strip data: URIs due to allow_unsafe_links = false
        $this->assertStringNotContainsString('data:text/html', $html);
    }

    public function test_onerror_handlers_are_removed(): void
    {
        $markdown = '<img onerror="alert(\'XSS\')" src="x">';
        $html = $this->service->parse($markdown);

        $this->assertStringNotContainsString('onerror', $html);
    }

    public function test_lists_render_correctly(): void
    {
        $markdown = "- Item 1\n- Item 2\n- Item 3";
        $html = $this->service->parse($markdown);

        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Item 1</li>', $html);
    }

    public function test_blockquotes_render_correctly(): void
    {
        $markdown = '> This is a quote';
        $html = $this->service->parse($markdown);

        $this->assertStringContainsString('<blockquote>', $html);
    }
}
