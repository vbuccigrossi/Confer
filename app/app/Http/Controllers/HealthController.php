<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    /**
     * Liveness probe - simple check that app is running
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Readiness probe - check dependencies are available
     */
    public function ready(): JsonResponse
    {
        $timeout = config('health.ready_db_timeout_ms', 500);
        
        $checks = [
            'database' => $this->checkDatabase($timeout),
            'redis' => $this->checkRedis($timeout),
        ];

        $ready = array_reduce($checks, fn($carry, $check) => $carry && $check['ok'], true);

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $ready ? 200 : 503);
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(int $timeoutMs): array
    {
        $start = microtime(true);
        
        try {
            // Set statement timeout for this connection
            DB::statement("SET statement_timeout = {$timeoutMs}");
            
            // Simple ping query
            DB::select('SELECT 1 as ping');
            
            $duration = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'ok' => true,
                'duration_ms' => $duration,
            ];
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ];
        }
    }

    /**
     * Check Redis connectivity
     */
    private function checkRedis(int $timeoutMs): array
    {
        $start = microtime(true);
        
        try {
            // Simple ping
            $pong = Redis::ping();
            
            $duration = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'ok' => $pong === 'PONG' || $pong === true,
                'duration_ms' => $duration,
            ];
        } catch (\Exception $e) {
            $duration = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
            ];
        }
    }
}
