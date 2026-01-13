<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MetricsController extends Controller
{
    public function index(): Response
    {
        // Check if metrics are enabled
        if (!config('metrics.enabled', false)) {
            abort(404);
        }

        // Optional basic auth
        if (config('metrics.basic_auth', false)) {
            $this->checkBasicAuth();
        }

        $metrics = $this->gatherMetrics();

        return response($metrics, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    private function checkBasicAuth(): void
    {
        $user = config('metrics.basic_auth_user');
        $pass = config('metrics.basic_auth_password');

        $authUser = $_SERVER['PHP_AUTH_USER'] ?? '';
        $authPass = $_SERVER['PHP_AUTH_PW'] ?? '';

        // Use timing-safe comparison to prevent timing attacks
        if (!hash_equals($user, $authUser) || !hash_equals($pass, $authPass)) {
            header('WWW-Authenticate: Basic realm="Metrics"');
            abort(401, 'Authentication required');
        }
    }

    private function gatherMetrics(): string
    {
        $prefix = config('metrics.prefix', 'latch');
        $output = [];

        // Database ping
        $dbPing = $this->measureDatabasePing();
        $output[] = "# HELP {$prefix}_db_ping_seconds Database ping latency in seconds";
        $output[] = "# TYPE {$prefix}_db_ping_seconds gauge";
        $output[] = "{$prefix}_db_ping_seconds " . number_format($dbPing, 6);

        // Request counter (from Redis)
        $requestsTotal = $this->getRequestsTotal();
        $output[] = "# HELP {$prefix}_requests_total Total HTTP requests";
        $output[] = "# TYPE {$prefix}_requests_total counter";
        foreach ($requestsTotal as $method => $count) {
            $output[] = "{$prefix}_requests_total{method=\"{$method}\"} {$count}";
        }

        // Queue jobs processed (from Redis)
        $queueJobs = $this->getQueueJobsProcessed();
        $output[] = "# HELP {$prefix}_queue_jobs_processed_total Total processed queue jobs";
        $output[] = "# TYPE {$prefix}_queue_jobs_processed_total counter";
        $output[] = "{$prefix}_queue_jobs_processed_total {$queueJobs}";

        // WebSocket connections (placeholder)
        $wsConnections = $this->getWebSocketConnections();
        $output[] = "# HELP {$prefix}_websocket_connections Active WebSocket connections";
        $output[] = "# TYPE {$prefix}_websocket_connections gauge";
        $output[] = "{$prefix}_websocket_connections {$wsConnections}";

        return implode("\n", $output) . "\n";
    }

    private function measureDatabasePing(): float
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
            return microtime(true) - $start;
        } catch (\Exception $e) {
            return -1;
        }
    }

    private function getRequestsTotal(): array
    {
        try {
            $key = config('metrics.redis_keys.requests_total');
            $data = Redis::hgetall($key);
            return $data ?: ['GET' => 0, 'POST' => 0];
        } catch (\Exception $e) {
            return ['GET' => 0, 'POST' => 0];
        }
    }

    private function getQueueJobsProcessed(): int
    {
        try {
            $key = config('metrics.redis_keys.queue_jobs_processed');
            return (int) Redis::get($key) ?: 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getWebSocketConnections(): int
    {
        // Placeholder - would integrate with Laravel WebSockets stats
        return 0;
    }
}
