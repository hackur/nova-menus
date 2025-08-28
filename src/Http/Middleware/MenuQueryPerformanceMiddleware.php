<?php

namespace Skylark\Menus\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Skylark\Menus\Services\QueryPerformanceMonitor;

class MenuQueryPerformanceMiddleware
{
    protected QueryPerformanceMonitor $monitor;

    public function __construct(QueryPerformanceMonitor $monitor)
    {
        $this->monitor = $monitor;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Only monitor if performance monitoring is enabled
        if (!config('menus.performance_monitoring', false)) {
            return $next($request);
        }

        // Start monitoring
        $this->monitor->startMonitoring();
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Process the request
        $response = $next($request);

        // Stop monitoring and collect statistics
        $stats = $this->monitor->stopMonitoring();
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        // Calculate additional metrics
        $totalTime = ($endTime - $startTime) * 1000; // milliseconds
        $memoryUsage = $endMemory - $startMemory;
        $peakMemory = memory_get_peak_usage(true);

        // Add headers with performance information (in debug mode)
        if (config('app.debug') && config('menus.performance_headers', false)) {
            $response->headers->set('X-Menu-Queries', $stats['total_queries']);
            $response->headers->set('X-Menu-Query-Time', $stats['total_time'] . 'ms');
            $response->headers->set('X-Menu-Request-Time', number_format($totalTime, 2) . 'ms');
            $response->headers->set('X-Menu-Memory-Usage', $this->formatBytes($memoryUsage));
            
            if ($stats['slow_queries_count'] > 0) {
                $response->headers->set('X-Menu-Slow-Queries', $stats['slow_queries_count']);
            }
        }

        // Log performance metrics if threshold is exceeded
        $performanceThreshold = config('menus.performance_log_threshold', 1000); // 1 second default
        
        if ($totalTime > $performanceThreshold || $stats['slow_queries_count'] > 0) {
            $this->logPerformanceMetrics($request, $stats, $totalTime, $memoryUsage);
        }

        // Store performance data for debugging (optional)
        if (config('menus.store_performance_data', false)) {
            $this->storePerformanceData($request, $stats, $totalTime, $memoryUsage, $peakMemory);
        }

        return $response;
    }

    /**
     * Log performance metrics
     */
    protected function logPerformanceMetrics(Request $request, array $stats, float $totalTime, int $memoryUsage): void
    {
        $logData = [
            'url' => $request->url(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'total_time' => number_format($totalTime, 2) . 'ms',
            'query_time' => $stats['total_time'] . 'ms',
            'query_count' => $stats['total_queries'],
            'slow_queries' => $stats['slow_queries_count'],
            'memory_usage' => $this->formatBytes($memoryUsage),
        ];

        // Add slow query details if present
        if ($stats['slow_queries_count'] > 0) {
            $logData['slow_query_details'] = array_map(function ($query) {
                return [
                    'sql' => $query['sql'],
                    'time' => $query['time'] . 'ms',
                    'bindings' => $query['bindings'],
                ];
            }, array_slice($stats['slow_queries'], 0, 3)); // Log first 3 slow queries
        }

        // Add optimization suggestions
        $suggestions = $this->monitor->getMenuOptimizationSuggestions();
        if (!empty($suggestions)) {
            $logData['optimization_suggestions'] = array_slice($suggestions, 0, 3);
        }

        Log::info('Menu query performance metrics', $logData);
    }

    /**
     * Store performance data for analysis
     */
    protected function storePerformanceData(
        Request $request,
        array $stats,
        float $totalTime,
        int $memoryUsage,
        int $peakMemory
    ): void {
        // This could store to database, cache, or external monitoring service
        $performanceData = [
            'timestamp' => now(),
            'url' => $request->url(),
            'method' => $request->method(),
            'user_id' => auth()->id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_time' => $totalTime,
            'query_stats' => $stats,
            'memory_usage' => $memoryUsage,
            'peak_memory' => $peakMemory,
            'optimization_suggestions' => $this->monitor->getMenuOptimizationSuggestions(),
        ];

        // Example: Store in cache for later analysis
        $cacheKey = 'menu_performance_' . now()->format('Y-m-d-H');
        $existingData = cache()->get($cacheKey, []);
        $existingData[] = $performanceData;
        
        // Keep only last 100 entries per hour
        if (count($existingData) > 100) {
            $existingData = array_slice($existingData, -100);
        }
        
        cache()->put($cacheKey, $existingData, now()->addHours(24));

        // Example: Send to external monitoring service
        if (config('menus.external_monitoring.enabled', false)) {
            $this->sendToExternalMonitoring($performanceData);
        }
    }

    /**
     * Send performance data to external monitoring service
     */
    protected function sendToExternalMonitoring(array $data): void
    {
        try {
            $endpoint = config('menus.external_monitoring.endpoint');
            $apiKey = config('menus.external_monitoring.api_key');

            if ($endpoint && $apiKey) {
                // Example implementation - adjust based on your monitoring service
                $client = new \GuzzleHttp\Client();
                $client->post($endpoint, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'service' => 'menu-management',
                        'metrics' => $data,
                    ],
                    'timeout' => 5, // Don't let monitoring slow down the app
                ]);
            }
        } catch (\Exception $e) {
            // Don't fail the request if monitoring fails
            Log::warning('Failed to send performance data to external monitoring', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format bytes to human readable format
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }

    /**
     * Generate performance summary for response
     */
    protected function generatePerformanceSummary(array $stats, float $totalTime): array
    {
        return [
            'request_time' => number_format($totalTime, 2) . 'ms',
            'database' => [
                'queries' => $stats['total_queries'],
                'time' => $stats['total_time'] . 'ms',
                'slow_queries' => $stats['slow_queries_count'],
                'average_time' => $stats['average_time'] . 'ms',
            ],
            'analysis' => [
                'potential_n_plus_one' => count($stats['query_analysis']['n_plus_one_potential']),
                'duplicate_queries' => count($stats['query_analysis']['duplicate_queries']),
                'optimization_suggestions' => count($this->monitor->getMenuOptimizationSuggestions()),
            ],
        ];
    }
}