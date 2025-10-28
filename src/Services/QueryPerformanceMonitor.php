<?php

namespace Skylark\Menus\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryPerformanceMonitor
{
    protected array $queries = [];

    protected array $slowQueries = [];

    protected float $slowQueryThreshold = 100.0; // milliseconds

    protected bool $isListening = false;

    public function __construct(float $slowQueryThreshold = 100.0)
    {
        $this->slowQueryThreshold = $slowQueryThreshold;
    }

    /**
     * Start monitoring database queries
     */
    public function startMonitoring(): void
    {
        if ($this->isListening) {
            return;
        }

        $this->queries = [];
        $this->slowQueries = [];
        $this->isListening = true;

        DB::listen(function ($query) {
            $this->recordQuery($query);
        });
    }

    /**
     * Stop monitoring and return statistics
     */
    public function stopMonitoring(): array
    {
        $this->isListening = false;

        return $this->getStatistics();
    }

    /**
     * Record a database query
     */
    protected function recordQuery($query): void
    {
        $queryData = [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time' => $query->time,
            'connection' => $query->connectionName,
            'timestamp' => microtime(true),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
        ];

        $this->queries[] = $queryData;

        // Track slow queries
        if ($query->time > $this->slowQueryThreshold) {
            $this->slowQueries[] = $queryData;

            // Log slow queries if enabled
            if (config('menus.log_slow_queries', false)) {
                Log::warning('Slow menu query detected', [
                    'sql' => $query->sql,
                    'time' => $query->time.'ms',
                    'bindings' => $query->bindings,
                ]);
            }
        }
    }

    /**
     * Get query statistics
     */
    public function getStatistics(): array
    {
        $totalQueries = count($this->queries);
        $totalTime = array_sum(array_column($this->queries, 'time'));
        $slowQueriesCount = count($this->slowQueries);

        return [
            'total_queries' => $totalQueries,
            'total_time' => round($totalTime, 2),
            'average_time' => $totalQueries > 0 ? round($totalTime / $totalQueries, 2) : 0,
            'slow_queries_count' => $slowQueriesCount,
            'slow_queries_percentage' => $totalQueries > 0 ? round(($slowQueriesCount / $totalQueries) * 100, 2) : 0,
            'queries' => $this->queries,
            'slow_queries' => $this->slowQueries,
            'query_analysis' => $this->analyzeQueries(),
        ];
    }

    /**
     * Analyze queries for performance patterns
     */
    protected function analyzeQueries(): array
    {
        if (empty($this->queries)) {
            return [];
        }

        $analysis = [
            'n_plus_one_potential' => [],
            'duplicate_queries' => [],
            'missing_indexes_potential' => [],
            'large_result_sets' => [],
        ];

        // Group queries by SQL pattern
        $queryPatterns = [];
        foreach ($this->queries as $query) {
            $pattern = $this->normalizeQuery($query['sql']);
            if (! isset($queryPatterns[$pattern])) {
                $queryPatterns[$pattern] = [];
            }
            $queryPatterns[$pattern][] = $query;
        }

        // Detect potential N+1 queries
        foreach ($queryPatterns as $pattern => $queries) {
            if (count($queries) > 5) {
                $analysis['n_plus_one_potential'][] = [
                    'pattern' => $pattern,
                    'count' => count($queries),
                    'total_time' => array_sum(array_column($queries, 'time')),
                    'example_sql' => $queries[0]['sql'],
                ];
            }
        }

        // Detect duplicate queries (exact matches)
        $exactQueries = [];
        foreach ($this->queries as $query) {
            $key = md5($query['sql'].serialize($query['bindings']));
            if (! isset($exactQueries[$key])) {
                $exactQueries[$key] = [];
            }
            $exactQueries[$key][] = $query;
        }

        foreach ($exactQueries as $queries) {
            if (count($queries) > 1) {
                $analysis['duplicate_queries'][] = [
                    'sql' => $queries[0]['sql'],
                    'bindings' => $queries[0]['bindings'],
                    'count' => count($queries),
                    'total_time' => array_sum(array_column($queries, 'time')),
                ];
            }
        }

        // Detect potential missing indexes (slow queries without WHERE clauses)
        foreach ($this->slowQueries as $query) {
            if (stripos($query['sql'], 'WHERE') === false && stripos($query['sql'], 'SELECT') === 0) {
                $analysis['missing_indexes_potential'][] = [
                    'sql' => $query['sql'],
                    'time' => $query['time'],
                    'suggestion' => 'Consider adding appropriate indexes or WHERE clauses',
                ];
            }
        }

        return $analysis;
    }

    /**
     * Normalize SQL query for pattern matching
     */
    protected function normalizeQuery(string $sql): string
    {
        // Replace common value patterns with placeholders
        $patterns = [
            '/\b\d+\b/' => '?',                    // Numbers
            "/'[^']*'/" => '?',                    // String literals
            '/\s+/' => ' ',                        // Multiple spaces
            '/\s*,\s*/' => ', ',                   // Comma spacing
        ];

        $normalized = $sql;
        foreach ($patterns as $pattern => $replacement) {
            $normalized = preg_replace($pattern, $replacement, $normalized);
        }

        return trim($normalized);
    }

    /**
     * Monitor a specific operation
     */
    public function monitor(callable $operation): array
    {
        $this->startMonitoring();

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $result = $operation();

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $stats = $this->stopMonitoring();

        $stats['operation_time'] = round(($endTime - $startTime) * 1000, 2); // milliseconds
        $stats['memory_usage'] = $endMemory - $startMemory;
        $stats['peak_memory'] = memory_get_peak_usage(true);
        $stats['result'] = $result;

        return $stats;
    }

    /**
     * Generate performance report
     */
    public function generateReport(): string
    {
        $stats = $this->getStatistics();

        $report = "Database Query Performance Report\n";
        $report .= "================================\n\n";

        $report .= "Summary:\n";
        $report .= "- Total Queries: {$stats['total_queries']}\n";
        $report .= "- Total Time: {$stats['total_time']}ms\n";
        $report .= "- Average Time: {$stats['average_time']}ms\n";
        $report .= "- Slow Queries: {$stats['slow_queries_count']} ({$stats['slow_queries_percentage']}%)\n\n";

        if (! empty($stats['query_analysis']['n_plus_one_potential'])) {
            $report .= "Potential N+1 Query Issues:\n";
            foreach ($stats['query_analysis']['n_plus_one_potential'] as $issue) {
                $report .= "- Pattern executed {$issue['count']} times ({$issue['total_time']}ms total)\n";
                $report .= '  SQL: '.substr($issue['example_sql'], 0, 100)."...\n";
            }
            $report .= "\n";
        }

        if (! empty($stats['query_analysis']['duplicate_queries'])) {
            $report .= "Duplicate Queries:\n";
            foreach ($stats['query_analysis']['duplicate_queries'] as $duplicate) {
                $report .= "- Query executed {$duplicate['count']} times ({$duplicate['total_time']}ms total)\n";
                $report .= '  SQL: '.substr($duplicate['sql'], 0, 100)."...\n";
            }
            $report .= "\n";
        }

        if (! empty($stats['slow_queries'])) {
            $report .= "Slowest Queries:\n";
            $slowest = array_slice(
                array_sort($stats['slow_queries'], function ($query) {
                    return -$query['time']; // Descending order
                }),
                0,
                5
            );

            foreach ($slowest as $query) {
                $report .= "- {$query['time']}ms: ".substr($query['sql'], 0, 100)."...\n";
            }
        }

        return $report;
    }

    /**
     * Get menu-specific query optimizations
     */
    public function getMenuOptimizationSuggestions(): array
    {
        $suggestions = [];
        $stats = $this->getStatistics();

        foreach ($this->queries as $query) {
            $sql = strtolower($query['sql']);

            // Check for menu-related queries without proper indexing
            if (strpos($sql, 'menu_items') !== false) {
                if ($query['time'] > $this->slowQueryThreshold) {
                    if (strpos($sql, 'parent_id') !== false && strpos($sql, 'order by') !== false) {
                        $suggestions[] = [
                            'type' => 'index',
                            'suggestion' => 'Consider adding composite index on (parent_id, position) for menu_items table',
                            'query' => $query['sql'],
                            'time' => $query['time'],
                        ];
                    }

                    if (strpos($sql, 'where') !== false && strpos($sql, 'menu_id') !== false) {
                        $suggestions[] = [
                            'type' => 'index',
                            'suggestion' => 'Consider adding index on menu_id for faster menu filtering',
                            'query' => $query['sql'],
                            'time' => $query['time'],
                        ];
                    }
                }

                // Check for missing eager loading
                if (strpos($sql, 'select * from') === 0) {
                    $suggestions[] = [
                        'type' => 'eager_loading',
                        'suggestion' => 'Use specific column selection instead of SELECT * for better performance',
                        'query' => $query['sql'],
                        'time' => $query['time'],
                    ];
                }
            }
        }

        // Check for N+1 issues specific to menu hierarchies
        $analysis = $stats['query_analysis'];
        foreach ($analysis['n_plus_one_potential'] as $nPlusOne) {
            if (strpos(strtolower($nPlusOne['example_sql']), 'menu_items') !== false) {
                $suggestions[] = [
                    'type' => 'n_plus_one',
                    'suggestion' => 'Use eager loading with ->with([\'children\', \'parent\']) to avoid N+1 queries',
                    'pattern' => $nPlusOne['pattern'],
                    'count' => $nPlusOne['count'],
                    'total_time' => $nPlusOne['total_time'],
                ];
            }
        }

        return array_unique($suggestions, SORT_REGULAR);
    }

    /**
     * Set slow query threshold
     */
    public function setSlowQueryThreshold(float $threshold): void
    {
        $this->slowQueryThreshold = $threshold;
    }

    /**
     * Get all recorded queries
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Get slow queries
     */
    public function getSlowQueries(): array
    {
        return $this->slowQueries;
    }

    /**
     * Clear recorded queries
     */
    public function clearQueries(): void
    {
        $this->queries = [];
        $this->slowQueries = [];
    }
}
