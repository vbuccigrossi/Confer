<?php

namespace App\Services;

use App\Jobs\DeliverOutboxEvent;
use App\Models\App;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\Http;

/**
 * Service for dispatching and delivering outbox events
 */
class OutboxDispatcher
{
    /**
     * Create and queue an outbox event
     */
    public function dispatch(App $app, string $eventType, array $payload): OutboxEvent
    {
        $event = OutboxEvent::create([
            'workspace_id' => $app->workspace_id,
            'app_id' => $app->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'delivery_status' => OutboxEvent::STATUS_PENDING,
            'attempt_count' => 0,
        ]);

        // Dispatch to queue
        DeliverOutboxEvent::dispatch($event)
            ->delay(now()->addSeconds($this->getBackoffDelay(0)));

        return $event;
    }

    /**
     * Attempt HTTP delivery of an outbox event
     */
    public function deliver(OutboxEvent $event): bool
    {
        $app = $event->app;

        if (!$app->callback_url) {
            $event->markFailed('No callback URL configured');
            return false;
        }

        try {
            $response = Http::timeout(config('apps.webhook_timeout', 10))
                ->post($app->callback_url, $event->payload);

            if ($response->successful()) {
                $event->markSuccess();
                return true;
            }

            $error = "HTTP {$response->status()}: " . $response->body();
            $event->incrementAttempts();

            if ($this->shouldRetry($event)) {
                // Will be retried via job backoff
                $event->update(['last_error' => $error]);
                return false;
            } else {
                $event->markFailed($error);
                return false;
            }
        } catch (\Exception $e) {
            $event->incrementAttempts();
            
            if ($this->shouldRetry($event)) {
                $event->update(['last_error' => $e->getMessage()]);
                return false;
            } else {
                $event->markFailed($e->getMessage());
                return false;
            }
        }
    }

    /**
     * Check if event should be retried
     */
    public function shouldRetry(OutboxEvent $event): bool
    {
        $maxRetries = config('apps.outbox_max_retries', 6);
        return $event->attempt_count < $maxRetries;
    }

    /**
     * Calculate backoff delay for attempt number
     */
    public function getBackoffDelay(int $attempt): int
    {
        $schedule = config('apps.outbox_backoff_schedule', [1, 5, 30, 120, 300, 600]);
        return $schedule[$attempt] ?? end($schedule);
    }
}
