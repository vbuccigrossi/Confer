<?php

namespace App\Console\Commands;

use App\Models\Poll;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CloseExpiredPolls extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'polls:close-expired {--dry-run : Show what would be closed without actually closing}';

    /**
     * The console command description.
     */
    protected $description = 'Close polls that have passed their closes_at time';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $expiredPolls = Poll::where('is_closed', false)
            ->whereNotNull('closes_at')
            ->where('closes_at', '<=', now())
            ->get();

        if ($expiredPolls->isEmpty()) {
            $this->info('No expired polls to close.');
            return 0;
        }

        $this->info("Found {$expiredPolls->count()} expired poll(s).");

        foreach ($expiredPolls as $poll) {
            $this->line("  Poll #{$poll->id}: \"{$poll->question}\"");
            $this->line("    Expired at: {$poll->closes_at}");
            $this->line("    Total votes: {$poll->getTotalVotes()}");

            if ($dryRun) {
                $this->info("    [DRY RUN] Would close poll");
                continue;
            }

            $poll->close();
            $this->info("    ✓ Closed");

            Log::info("CloseExpiredPolls: Closed poll #{$poll->id}");
        }

        return 0;
    }
}
