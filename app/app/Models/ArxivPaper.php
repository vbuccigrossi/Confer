<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks arxiv.org papers that have been posted to channels.
 * Used to prevent duplicate posts and track paper history.
 */
class ArxivPaper extends Model
{
    use HasFactory;

    protected $fillable = [
        'arxiv_id',
        'title',
        'summary',
        'authors',
        'categories',
        'primary_category',
        'pdf_url',
        'abs_url',
        'published_at',
        'updated_at_arxiv',
        'workspace_id',
        'conversation_id',
        'message_id',
        'posted_at',
    ];

    protected $casts = [
        'authors' => 'array',
        'categories' => 'array',
        'published_at' => 'datetime',
        'updated_at_arxiv' => 'datetime',
        'posted_at' => 'datetime',
    ];

    /**
     * AI-related arxiv categories to filter for.
     */
    public const AI_CATEGORIES = [
        'cs.AI',    // Artificial Intelligence
        'cs.LG',    // Machine Learning
        'cs.CL',    // Computation and Language (NLP)
        'cs.CV',    // Computer Vision
        'cs.NE',    // Neural and Evolutionary Computing
        'cs.RO',    // Robotics
        'stat.ML',  // Machine Learning (Statistics)
    ];

    /**
     * Human-readable category names.
     */
    public const CATEGORY_NAMES = [
        'cs.AI' => 'Artificial Intelligence',
        'cs.LG' => 'Machine Learning',
        'cs.CL' => 'NLP & Language',
        'cs.CV' => 'Computer Vision',
        'cs.NE' => 'Neural Computing',
        'cs.RO' => 'Robotics',
        'stat.ML' => 'Statistical ML',
    ];

    /**
     * Get workspace this paper was posted to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get conversation (channel) this paper was posted to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the message that was posted for this paper.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Check if this paper has been posted.
     */
    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    /**
     * Check if paper was already posted to a specific workspace.
     */
    public static function wasPostedToWorkspace(string $arxivId, int $workspaceId): bool
    {
        return static::where('arxiv_id', $arxivId)
            ->where('workspace_id', $workspaceId)
            ->whereNotNull('posted_at')
            ->exists();
    }

    /**
     * Get human-readable category name.
     */
    public function getCategoryName(): string
    {
        return self::CATEGORY_NAMES[$this->primary_category] ?? $this->primary_category;
    }

    /**
     * Get formatted author list.
     */
    public function getFormattedAuthors(int $maxAuthors = 3): string
    {
        $authors = $this->authors;

        if (count($authors) <= $maxAuthors) {
            return implode(', ', $authors);
        }

        $displayed = array_slice($authors, 0, $maxAuthors);
        $remaining = count($authors) - $maxAuthors;

        return implode(', ', $displayed) . " (+{$remaining} more)";
    }

    /**
     * Format this paper as a Markdown message.
     */
    public function toMarkdown(bool $includeSummary = true): string
    {
        $categoryEmoji = $this->getCategoryEmoji();
        $categoryName = $this->getCategoryName();

        $md = "{$categoryEmoji} **{$this->title}**\n\n";
        $md .= "**Authors:** {$this->getFormattedAuthors()}\n";
        $md .= "**Category:** {$categoryName}\n";

        if ($includeSummary && $this->summary) {
            $summary = $this->getTruncatedSummary(300);
            $md .= "\n> {$summary}\n";
        }

        $md .= "\n[PDF]({$this->pdf_url}) | [Abstract]({$this->abs_url})";

        return $md;
    }

    /**
     * Get emoji for category.
     */
    public function getCategoryEmoji(): string
    {
        $emojis = [
            'cs.AI' => '🤖',
            'cs.LG' => '📊',
            'cs.CL' => '💬',
            'cs.CV' => '👁️',
            'cs.NE' => '🧠',
            'cs.RO' => '🦾',
            'stat.ML' => '📈',
        ];

        return $emojis[$this->primary_category] ?? '📄';
    }

    /**
     * Get truncated summary.
     */
    public function getTruncatedSummary(int $maxLength = 300): string
    {
        if (!$this->summary) {
            return '';
        }

        $summary = preg_replace('/\s+/', ' ', $this->summary);

        if (strlen($summary) <= $maxLength) {
            return $summary;
        }

        return substr($summary, 0, $maxLength - 3) . '...';
    }
}
