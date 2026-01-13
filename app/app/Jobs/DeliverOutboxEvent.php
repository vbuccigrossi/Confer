<?php

namespace App\Jobs;

use App\Models\OutboxEvent;
use App\Services\OutboxDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverOutboxEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public OutboxEvent $event;
    public $tries;

    /**
     * Create a new job instance.
     */
    public function __construct(OutboxEvent $event)
    {
        $this->event = $event;
        $this->tries = config('apps.outbox_max_retries', 6);
    }

    /**
     * Execute the job.
     */
    public function handle(OutboxDispatcher $dispatcher): void
    {
        // Skip if already successful or failed
        if (!$this->event->isPending()) {
            return;
        }

        $success = $dispatcher->deliver($this->event);

        // If delivery failed and should retry, re-queue with backoff
        if (!$success && $dispatcher->shouldRetry($this->event)) {
            $delay = $dispatcher->getBackoffDelay($this->event->attempt_count);
            self::dispatch($this->event)->delay(now()->addSeconds($delay));
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->event->markFailed($exception->getMessage());
    }
}
