<?php

namespace Skylark\Menus\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Skylark\Menus\Models\MenuItem;
use Skylark\Menus\Services\QueryPerformanceMonitor;

class AnalyzeMenuPerformanceCommand extends Command
{
    protected $signature = 'menus:analyze-performance 
                           {--reset : Reset performance data}
                           {--export= : Export results to file}
                           {--threshold=100 : Slow query threshold in milliseconds}
                           {--sample-size=1000 : Number of operations to test}';

    protected $description = 'Analyze menu system performance and generate optimization recommendations';

    protected QueryPerformanceMonitor $monitor;

    public function __construct(QueryPerformanceMonitor $monitor)
    {
        parent::__construct();
        $this->monitor = $monitor;
    }

    public function handle(): int
    {
        $this->info('🔍 Starting Menu Performance Analysis...');
        
        if ($this->option('reset')) {
            $this->resetPerformanceData();
        }

        $threshold = (float) $this->option('threshold');
        $sampleSize = (int) $this->option('sample-size');
        
        $this->monitor->setSlowQueryThreshold($threshold);

        // Analyze different menu operations
        $results = [
            'menu_listing' => $this->analyzeMenuListing(),
            'menu_item_retrieval' => $this->analyzeMenuItemRetrieval($sampleSize),
            'hierarchy_operations' => $this->analyzeHierarchyOperations(),
            'visibility_filtering' => $this->analyzeVisibilityFiltering(),
            'nested_set_operations' => $this->analyzeNestedSetOperations(),
        ];

        // Generate comprehensive report
        $report = $this->generatePerformanceReport($results);
        
        $this->displayReport($report);

        // Export results if requested
        if ($exportPath = $this->option('export')) {
            $this->exportReport($report, $exportPath);
        }

        $this->info('✅ Performance analysis completed!');
        
        return 0;
    }

    /**
     * Analyze menu listing performance
     */
    protected function analyzeMenuListing(): array
    {
        $this->info('Analyzing menu listing performance...');
        
        return $this->monitor->monitor(function () {
            // Test various menu listing scenarios
            $results = [];
            
            // Basic menu listing
            $results['basic_listing'] = MenuItem::roots()->get();
            
            // Menu listing with counts
            $results['with_counts'] = MenuItem::roots()
                ->withCount('children')
                ->get();
            
            // Menu listing with eager loading
            $results['eager_loaded'] = MenuItem::roots()
                ->with(['children' => function ($query) {
                    $query->limit(5);
                }])
                ->get();
                
            return $results;
        });
    }

    /**
     * Analyze menu item retrieval performance
     */
    protected function analyzeMenuItemRetrieval(int $sampleSize): array
    {
        $this->info('Analyzing menu item retrieval performance...');
        
        // Get sample of menu items
        $menuItems = MenuItem::inRandomOrder()->limit(min($sampleSize, 100))->get();
        
        if ($menuItems->isEmpty()) {
            $this->warn('No menu items found for testing. Creating sample data...');
            $this->createSampleMenuData();
            $menuItems = MenuItem::inRandomOrder()->limit(10)->get();
        }

        return $this->monitor->monitor(function () use ($menuItems) {
            $results = [];
            
            foreach ($menuItems->take(10) as $item) {
                // Test different retrieval methods
                $results['find_operations'][] = MenuItem::find($item->id);
                $results['with_children'][] = MenuItem::with('children')->find($item->id);
                $results['with_ancestors'][] = MenuItem::with('ancestors')->find($item->id);
            }
            
            return $results;
        });
    }

    /**
     * Analyze hierarchy operations performance
     */
    protected function analyzeHierarchyOperations(): array
    {
        $this->info('Analyzing hierarchy operations performance...');
        
        return $this->monitor->monitor(function () {
            $results = [];
            
            // Find a menu item with children
            $parent = MenuItem::has('children')->first();
            
            if ($parent) {
                // Test hierarchy methods
                $results['children'] = $parent->children;
                $results['descendants'] = $parent->descendants;
                $results['ancestors'] = $parent->ancestors;
                $results['siblings'] = $parent->siblings();
                
                // Test nested set specific operations
                if (method_exists($parent, 'isDescendantOf')) {
                    $root = MenuItem::roots()->first();
                    if ($root && $root->id !== $parent->id) {
                        $results['is_descendant'] = $parent->isDescendantOf($root);
                    }
                }
            }
            
            return $results;
        });
    }

    /**
     * Analyze visibility filtering performance
     */
    protected function analyzeVisibilityFiltering(): array
    {
        $this->info('Analyzing visibility filtering performance...');
        
        return $this->monitor->monitor(function () {
            $results = [];
            
            // Test visibility scopes
            $results['visible_items'] = MenuItem::visible()->count();
            $results['active_items'] = MenuItem::where('is_active', true)->count();
            
            // Test temporal filtering
            $now = now();
            $results['visible_at_now'] = MenuItem::isVisibleAt($now)->count();
            
            // Test complex visibility queries
            $results['complex_visibility'] = MenuItem::where('is_active', true)
                ->where(function ($query) use ($now) {
                    $query->whereNull('display_at')
                          ->orWhere('display_at', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('hide_at')
                          ->orWhere('hide_at', '>', $now);
                })
                ->count();
                
            return $results;
        });
    }

    /**
     * Analyze nested set operations performance
     */
    protected function analyzeNestedSetOperations(): array
    {
        $this->info('Analyzing nested set operations performance...');
        
        return $this->monitor->monitor(function () {
            $results = [];
            
            // Test nested set queries if available
            $menuItem = MenuItem::first();
            
            if ($menuItem && method_exists($menuItem, 'lft')) {
                // These are typical nested set operations
                $results['tree_query'] = MenuItem::whereNull('parent_id')
                    ->with('children')
                    ->get();
                    
                $results['depth_query'] = DB::table('menu_items')
                    ->selectRaw('*, (rgt - lft - 1) / 2 as descendants_count')
                    ->where('menu_id', $menuItem->menu_id)
                    ->get();
            } else {
                // Fallback to adjacency list operations
                $results['tree_query'] = MenuItem::whereNull('parent_id')
                    ->with(['children.children'])
                    ->get();
            }
            
            return $results;
        });
    }

    /**
     * Generate comprehensive performance report
     */
    protected function generatePerformanceReport(array $results): array
    {
        $report = [
            'summary' => $this->generateSummary($results),
            'detailed_results' => $results,
            'optimization_recommendations' => $this->generateOptimizationRecommendations($results),
            'database_analysis' => $this->analyzeDatabaseStructure(),
            'generated_at' => now()->toISOString(),
        ];

        return $report;
    }

    /**
     * Generate performance summary
     */
    protected function generateSummary(array $results): array
    {
        $summary = [
            'total_queries' => 0,
            'total_time' => 0,
            'slow_queries' => 0,
            'fastest_operation' => null,
            'slowest_operation' => null,
        ];

        $operationTimes = [];

        foreach ($results as $operation => $result) {
            $summary['total_queries'] += $result['total_queries'];
            $summary['total_time'] += $result['total_time'];
            $summary['slow_queries'] += $result['slow_queries_count'];
            
            $operationTimes[$operation] = $result['operation_time'];
        }

        if (!empty($operationTimes)) {
            $summary['fastest_operation'] = [
                'name' => array_search(min($operationTimes), $operationTimes),
                'time' => min($operationTimes) . 'ms'
            ];
            
            $summary['slowest_operation'] = [
                'name' => array_search(max($operationTimes), $operationTimes),
                'time' => max($operationTimes) . 'ms'
            ];
        }

        return $summary;
    }

    /**
     * Generate optimization recommendations
     */
    protected function generateOptimizationRecommendations(array $results): array
    {
        $recommendations = [];
        
        foreach ($results as $operation => $result) {
            // Analyze each operation for optimization opportunities
            if ($result['slow_queries_count'] > 0) {
                $recommendations[] = [
                    'operation' => $operation,
                    'issue' => 'Slow queries detected',
                    'recommendation' => $this->getSlowQueryRecommendation($operation, $result),
                    'priority' => 'high',
                ];
            }
            
            if ($result['total_queries'] > 10) {
                $recommendations[] = [
                    'operation' => $operation,
                    'issue' => 'High query count',
                    'recommendation' => $this->getHighQueryCountRecommendation($operation, $result),
                    'priority' => 'medium',
                ];
            }

            // Check for N+1 query patterns
            if (!empty($result['query_analysis']['n_plus_one_potential'])) {
                $recommendations[] = [
                    'operation' => $operation,
                    'issue' => 'Potential N+1 queries',
                    'recommendation' => 'Implement eager loading with ->with() relationships',
                    'priority' => 'high',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Get slow query recommendation
     */
    protected function getSlowQueryRecommendation(string $operation, array $result): string
    {
        $recommendations = [
            'menu_listing' => 'Consider adding indexes on frequently queried columns and using pagination',
            'menu_item_retrieval' => 'Add database indexes on id, parent_id, and menu_id columns',
            'hierarchy_operations' => 'Consider implementing nested set model for better hierarchy performance',
            'visibility_filtering' => 'Add composite indexes on (is_active, display_at, hide_at)',
            'nested_set_operations' => 'Ensure nested set columns (lft, rgt, depth) are properly indexed',
        ];

        return $recommendations[$operation] ?? 'Review query execution plan and add appropriate indexes';
    }

    /**
     * Get high query count recommendation
     */
    protected function getHighQueryCountRecommendation(string $operation, array $result): string
    {
        return 'Consider using eager loading, query optimization, or caching to reduce query count';
    }

    /**
     * Analyze database structure
     */
    protected function analyzeDatabaseStructure(): array
    {
        $analysis = [
            'table_info' => [],
            'indexes' => [],
            'recommendations' => [],
        ];

        try {
            // Get table information
            $tableInfo = DB::select("SHOW TABLE STATUS LIKE 'menu_items'");
            if (!empty($tableInfo)) {
                $info = $tableInfo[0];
                $analysis['table_info'] = [
                    'rows' => $info->Rows ?? 0,
                    'data_length' => $info->Data_length ?? 0,
                    'index_length' => $info->Index_length ?? 0,
                    'engine' => $info->Engine ?? 'unknown',
                ];
            }

            // Get index information
            $indexes = DB::select("SHOW INDEXES FROM menu_items");
            $analysis['indexes'] = array_map(function ($index) {
                return [
                    'name' => $index->Key_name ?? '',
                    'column' => $index->Column_name ?? '',
                    'unique' => ($index->Non_unique ?? 1) == 0,
                ];
            }, $indexes);

            // Generate index recommendations
            $analysis['recommendations'] = $this->generateIndexRecommendations($analysis['indexes']);

        } catch (\Exception $e) {
            $analysis['error'] = 'Could not analyze database structure: ' . $e->getMessage();
        }

        return $analysis;
    }

    /**
     * Generate index recommendations
     */
    protected function generateIndexRecommendations(array $indexes): array
    {
        $recommendations = [];
        $existingIndexes = array_column($indexes, 'column');

        $recommendedIndexes = [
            'menu_id' => 'For filtering items by menu',
            'parent_id' => 'For hierarchy queries',
            'position' => 'For ordering items',
            'is_active' => 'For visibility filtering',
            'display_at' => 'For temporal visibility',
            'hide_at' => 'For temporal visibility',
        ];

        foreach ($recommendedIndexes as $column => $reason) {
            if (!in_array($column, $existingIndexes)) {
                $recommendations[] = [
                    'type' => 'missing_index',
                    'column' => $column,
                    'reason' => $reason,
                    'sql' => "ALTER TABLE menu_items ADD INDEX idx_{$column} ({$column});",
                ];
            }
        }

        // Recommend composite indexes
        $compositeIndexes = [
            ['columns' => ['menu_id', 'parent_id'], 'reason' => 'For menu hierarchy queries'],
            ['columns' => ['parent_id', 'position'], 'reason' => 'For ordered child queries'],
            ['columns' => ['is_active', 'display_at', 'hide_at'], 'reason' => 'For visibility filtering'],
        ];

        foreach ($compositeIndexes as $index) {
            $indexName = 'idx_' . implode('_', $index['columns']);
            $columnList = implode(', ', $index['columns']);
            
            $recommendations[] = [
                'type' => 'composite_index',
                'columns' => $index['columns'],
                'reason' => $index['reason'],
                'sql' => "ALTER TABLE menu_items ADD INDEX {$indexName} ({$columnList});",
            ];
        }

        return $recommendations;
    }

    /**
     * Display performance report
     */
    protected function displayReport(array $report): void
    {
        $this->newLine();
        $this->info('📊 MENU PERFORMANCE ANALYSIS REPORT');
        $this->info('=====================================');
        
        // Summary
        $summary = $report['summary'];
        $this->info("Total Queries: {$summary['total_queries']}");
        $this->info("Total Time: {$summary['total_time']}ms");
        $this->info("Slow Queries: {$summary['slow_queries']}");
        
        if ($summary['fastest_operation']) {
            $this->info("Fastest Operation: {$summary['fastest_operation']['name']} ({$summary['fastest_operation']['time']})");
        }
        
        if ($summary['slowest_operation']) {
            $this->warn("Slowest Operation: {$summary['slowest_operation']['name']} ({$summary['slowest_operation']['time']})");
        }

        $this->newLine();

        // Optimization recommendations
        if (!empty($report['optimization_recommendations'])) {
            $this->warn('🔧 OPTIMIZATION RECOMMENDATIONS:');
            foreach ($report['optimization_recommendations'] as $rec) {
                $priority = strtoupper($rec['priority']);
                $this->line("[$priority] {$rec['operation']}: {$rec['issue']}");
                $this->line("   💡 {$rec['recommendation']}");
            }
            $this->newLine();
        }

        // Database recommendations
        if (!empty($report['database_analysis']['recommendations'])) {
            $this->warn('🗄️  DATABASE RECOMMENDATIONS:');
            foreach ($report['database_analysis']['recommendations'] as $rec) {
                $this->line("{$rec['type']}: {$rec['reason']}");
                $this->line("   SQL: {$rec['sql']}");
            }
        }
    }

    /**
     * Export report to file
     */
    protected function exportReport(array $report, string $path): void
    {
        $content = json_encode($report, JSON_PRETTY_PRINT);
        file_put_contents($path, $content);
        $this->info("📄 Report exported to: {$path}");
    }

    /**
     * Reset performance data
     */
    protected function resetPerformanceData(): void
    {
        $this->info('🗑️  Resetting performance data...');
        
        // Clear cached performance data
        $pattern = 'menu_performance_*';
        Cache::flush(); // In production, you'd want more targeted cache clearing
        
        $this->info('Performance data cleared.');
    }

    /**
     * Create sample menu data for testing
     */
    protected function createSampleMenuData(): void
    {
        $this->info('Creating sample menu data for performance testing...');
        
        // Create a test menu with some items
        $menu = MenuItem::factory()->asMenu()->create([
            'name' => 'Performance Test Menu',
            'slug' => 'performance-test-menu',
        ]);

        // Create some menu items
        MenuItem::factory()->count(10)->forMenu($menu)->create();

        $this->info('Sample data created.');
    }
}