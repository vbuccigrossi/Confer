<?php

namespace App\Console\Commands;

use App\Models\ArxivPaper;
use App\Models\BotInstallation;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use App\Events\MessageCreated;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Fetches latest AI papers from arxiv.org and posts them to configured channels.
 *
 * The command queries the arxiv API for papers in AI-related categories,
 * filters out papers already posted, and creates messages in the target
 * conversation for each new paper.
 */
class FetchArxivPapers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'arxiv:fetch
        {--workspace= : Specific workspace ID to fetch for (optional, fetches for all if not specified)}
        {--max=10 : Maximum number of papers to fetch per category}
        {--dry-run : Show what would be posted without actually posting}
        {--categories= : Comma-separated list of categories (default: all AI categories)}';

    /**
     * The console command description.
     */
    protected $description = 'Fetch latest AI papers from arxiv.org and post to configured channels';

    /**
     * The arxiv API base URL.
     */
    private const ARXIV_API_URL = 'https://export.arxiv.org/api/query';

    /**
     * Bot slug for the arxiv news bot.
     */
    private const BOT_SLUG = 'arxiv-news-bot';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $maxPapers = (int) $this->option('max');
        $workspaceId = $this->option('workspace');

        // Parse categories option
        $categoriesOption = $this->option('categories');
        $categories = $categoriesOption
            ? explode(',', $categoriesOption)
            : ArxivPaper::AI_CATEGORIES;

        $this->info('Fetching latest AI papers from arxiv.org...');
        $this->info('Categories: ' . implode(', ', $categories));

        if ($dryRun) {
            $this->warn('[DRY RUN MODE - No messages will be posted]');
        }

        // Get bot installations
        $installations = $this->getBotInstallations($workspaceId);

        if ($installations->isEmpty()) {
            $this->warn('No arxiv-news-bot installations found.');
            return 0;
        }

        $this->info("Found {$installations->count()} bot installation(s).");

        // Fetch papers from arxiv
        $papers = $this->fetchPapersFromArxiv($categories, $maxPapers);

        if (empty($papers)) {
            $this->warn('No papers found from arxiv API.');
            return 0;
        }

        $this->info("Fetched " . count($papers) . " paper(s) from arxiv.");

        // Process each installation
        $totalPosted = 0;
        foreach ($installations as $installation) {
            $posted = $this->processInstallation($installation, $papers, $dryRun);
            $totalPosted += $posted;
        }

        $this->info("Complete! Posted {$totalPosted} paper(s).");

        return 0;
    }

    /**
     * Get active bot installations for arxiv-news-bot.
     */
    private function getBotInstallations(?string $workspaceId)
    {
        $query = BotInstallation::whereHas('bot', function ($q) {
            $q->where('slug', self::BOT_SLUG);
        })->where('is_active', true);

        if ($workspaceId) {
            $query->where('workspace_id', $workspaceId);
        }

        return $query->with(['bot', 'workspace'])->get();
    }

    /**
     * Fetch papers from arxiv API.
     *
     * @param array<string> $categories
     * @return array<int, array<string, mixed>>
     */
    private function fetchPapersFromArxiv(array $categories, int $maxPapers): array
    {
        // Build search query for AI categories
        // Note: We use +OR+ which needs to remain unencoded for arxiv API
        $categoryQuery = implode('+OR+', array_map(fn($c) => "cat:{$c}", $categories));

        // Build URL manually to avoid over-encoding of + signs
        $params = [
            'start' => 0,
            'max_results' => $maxPapers * count($categories),
            'sortBy' => 'submittedDate',
            'sortOrder' => 'descending',
        ];

        // Build the URL - keep search_query separate to preserve + signs
        $url = self::ARXIV_API_URL . '?search_query=' . urlencode($categoryQuery) . '&' . http_build_query($params);
        // Unescape the + signs that were encoded as %2B
        $url = str_replace('%2B', '+', $url);
        $this->line("Querying: {$url}");

        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                $this->error("arxiv API returned HTTP {$response->status()}");
                Log::error("arxiv API error", ['status' => $response->status(), 'body' => $response->body()]);
                return [];
            }

            return $this->parseArxivResponse($response->body());
        } catch (\Exception $e) {
            $this->error("Failed to fetch from arxiv: " . $e->getMessage());
            Log::error("arxiv fetch failed", ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Parse the arxiv Atom feed response.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseArxivResponse(string $xmlContent): array
    {
        try {
            $xml = new SimpleXMLElement($xmlContent);
        } catch (\Exception $e) {
            $this->error("Failed to parse XML: " . $e->getMessage());
            return [];
        }

        // Register namespaces
        $namespaces = $xml->getNamespaces(true);
        $xml->registerXPathNamespace('atom', 'http://www.w3.org/2005/Atom');
        $xml->registerXPathNamespace('arxiv', 'http://arxiv.org/schemas/atom');

        $papers = [];
        foreach ($xml->entry as $entry) {
            $paper = $this->parseEntry($entry, $namespaces);
            if ($paper) {
                $papers[] = $paper;
            }
        }

        return $papers;
    }

    /**
     * Parse a single arxiv entry.
     *
     * @return array<string, mixed>|null
     */
    private function parseEntry(SimpleXMLElement $entry, array $namespaces): ?array
    {
        // Extract arxiv ID from the id URL
        $idUrl = (string) $entry->id;
        if (!preg_match('/abs\/(\d+\.\d+)/', $idUrl, $matches)) {
            return null;
        }
        $arxivId = $matches[1];

        // Get authors
        $authors = [];
        foreach ($entry->author as $author) {
            $authors[] = (string) $author->name;
        }

        // Get categories from regular category elements
        $categories = [];
        foreach ($entry->category as $cat) {
            $term = (string) $cat['term'];
            $categories[] = $term;
        }

        // Get primary category from arxiv namespace element
        $primaryCategory = null;
        $arxivNs = $namespaces['arxiv'] ?? 'http://arxiv.org/schemas/atom';
        $arxivElements = $entry->children($arxivNs);
        if (isset($arxivElements->primary_category)) {
            $attrs = $arxivElements->primary_category->attributes();
            $primaryCategory = (string) $attrs['term'];
        }

        // Fallback to first category if primary not found
        if (!$primaryCategory && !empty($categories)) {
            $primaryCategory = $categories[0];
        }

        // Only include if primary category is an AI category
        if (!in_array($primaryCategory, ArxivPaper::AI_CATEGORIES)) {
            return null;
        }

        // Get links
        $pdfUrl = null;
        $absUrl = null;
        foreach ($entry->link as $link) {
            $type = (string) $link['type'];
            $href = (string) $link['href'];

            if ($type === 'application/pdf') {
                $pdfUrl = $href;
            } elseif ($link['rel'] === 'alternate') {
                $absUrl = $href;
            }
        }

        // Fallback URLs
        if (!$absUrl) {
            $absUrl = "https://arxiv.org/abs/{$arxivId}";
        }
        if (!$pdfUrl) {
            $pdfUrl = "https://arxiv.org/pdf/{$arxivId}.pdf";
        }

        return [
            'arxiv_id' => $arxivId,
            'title' => trim((string) $entry->title),
            'summary' => trim((string) $entry->summary),
            'authors' => $authors,
            'categories' => $categories,
            'primary_category' => $primaryCategory,
            'pdf_url' => $pdfUrl,
            'abs_url' => $absUrl,
            'published_at' => (string) $entry->published,
            'updated_at_arxiv' => (string) $entry->updated,
        ];
    }

    /**
     * Process papers for a single bot installation.
     *
     * @param array<int, array<string, mixed>> $papers
     */
    private function processInstallation(BotInstallation $installation, array $papers, bool $dryRun): int
    {
        $workspace = $installation->workspace;
        $config = $installation->config ?? [];

        // Get target conversation from config
        $conversationId = $config['conversation_id'] ?? null;

        if (!$conversationId) {
            $this->warn("  Workspace #{$workspace->id} ({$workspace->name}): No conversation configured");
            return 0;
        }

        $conversation = Conversation::find($conversationId);
        if (!$conversation) {
            $this->warn("  Workspace #{$workspace->id}: Conversation #{$conversationId} not found");
            return 0;
        }

        $this->info("  Processing workspace: {$workspace->name} -> #{$conversation->name}");

        // Get bot user ID for posting
        $botUserId = $installation->bot->created_by_user_id;

        // Get configuration options
        $maxPapersPerRun = (int) ($config['max_papers_per_run'] ?? 5);
        $includeSummary = (bool) ($config['include_summary'] ?? true);
        $filterCategories = $config['filter_categories'] ?? [];

        $postedCount = 0;

        foreach ($papers as $paperData) {
            if ($postedCount >= $maxPapersPerRun) {
                $this->line("    Reached max papers limit ({$maxPapersPerRun})");
                break;
            }

            // Skip if category filter is set and doesn't match
            if (!empty($filterCategories) && !in_array($paperData['primary_category'], $filterCategories)) {
                continue;
            }

            // Check if already posted to this workspace
            if (ArxivPaper::wasPostedToWorkspace($paperData['arxiv_id'], $workspace->id)) {
                $this->line("    Skipping {$paperData['arxiv_id']} (already posted)");
                continue;
            }

            // Create the paper record
            $paper = new ArxivPaper([
                'arxiv_id' => $paperData['arxiv_id'],
                'title' => $paperData['title'],
                'summary' => $paperData['summary'],
                'authors' => $paperData['authors'],
                'categories' => $paperData['categories'],
                'primary_category' => $paperData['primary_category'],
                'pdf_url' => $paperData['pdf_url'],
                'abs_url' => $paperData['abs_url'],
                'published_at' => $paperData['published_at'],
                'updated_at_arxiv' => $paperData['updated_at_arxiv'],
                'workspace_id' => $workspace->id,
                'conversation_id' => $conversationId,
            ]);

            // Generate message content
            $messageContent = $paper->toMarkdown($includeSummary);

            $this->line("    Posting: {$paperData['arxiv_id']} - " . substr($paperData['title'], 0, 50) . '...');

            if ($dryRun) {
                $this->info("      [DRY RUN] Would post message");
                continue;
            }

            // Create message
            try {
                $message = Message::create([
                    'conversation_id' => $conversationId,
                    'user_id' => $botUserId,
                    'body_md' => $messageContent,
                    'is_system' => false,
                ]);

                // Update paper record with message ID and posted timestamp
                $paper->message_id = $message->id;
                $paper->posted_at = now();
                $paper->save();

                // Broadcast the message via WebSocket
                broadcast(new MessageCreated($message->load('user')))->toOthers();

                // Send push notifications to channel members
                app(NotificationService::class)->sendNewMessagePushNotifications($message);

                $postedCount++;
                $this->info("      Posted (message #{$message->id})");
            } catch (\Exception $e) {
                $this->error("      Failed: " . $e->getMessage());
                Log::error("arxiv paper post failed", [
                    'arxiv_id' => $paperData['arxiv_id'],
                    'workspace_id' => $workspace->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $postedCount;
    }
}
