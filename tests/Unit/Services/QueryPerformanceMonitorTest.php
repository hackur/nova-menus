<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Skylark\Menus\Models\MenuItem;
use Skylark\Menus\Services\QueryPerformanceMonitor;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
    $this->monitor = new QueryPerformanceMonitor();
});

describe('QueryPerformanceMonitor', function () {
    test('can start and stop monitoring', function () {
        $this->monitor->startMonitoring();
        
        // Perform some queries
        MenuItem::factory()->asMenu()->create(['name' => 'Test Menu']);
        
        $stats = $this->monitor->stopMonitoring();
        
        expect($stats['total_queries'])->toBeGreaterThan(0);
        expect($stats['total_time'])->toBeGreaterThan(0);
        expect($stats)->toHaveKey('queries');
        expect($stats)->toHaveKey('slow_queries');
    });

    test('can detect slow queries', function () {
        $this->monitor->setSlowQueryThreshold(0.01); // Very low threshold to catch queries
        $this->monitor->startMonitoring();
        
        // Perform a query that should be considered slow
        MenuItem::factory()->count(5)->asMenu()->create();
        
        $stats = $this->monitor->stopMonitoring();
        
        expect($stats['slow_queries_count'])->toBeGreaterThan(0);
        expect($stats['slow_queries'])->toBeArray();
    });

    test('can monitor specific operations', function () {
        $result = $this->monitor->monitor(function () {
            $menu = MenuItem::factory()->asMenu()->create(['name' => 'Monitored Menu']);
            $items = MenuItem::factory()->count(3)->forMenu($menu)->create();
            
            return [
                'menu' => $menu,
                'items' => $items,
                'count' => MenuItem::where('menu_id', $menu->id)->count(),
            ];
        });
        
        expect($result)->toHaveKey('total_queries');
        expect($result)->toHaveKey('operation_time');
        expect($result)->toHaveKey('memory_usage');
        expect($result)->toHaveKey('result');
        expect($result['result']['count'])->toBe(3);
    });

    test('can analyze query patterns', function () {
        $this->monitor->startMonitoring();
        
        // Create duplicate queries to test N+1 detection
        $menu = MenuItem::factory()->asMenu()->create();
        $items = MenuItem::factory()->count(5)->forMenu($menu)->create();
        
        // Simulate N+1 query pattern
        foreach ($items as $item) {
            MenuItem::where('parent_id', $item->id)->get();
        }
        
        $stats = $this->monitor->stopMonitoring();
        $analysis = $stats['query_analysis'];
        
        expect($analysis)->toHaveKey('n_plus_one_potential');
        expect($analysis)->toHaveKey('duplicate_queries');
        expect($analysis['n_plus_one_potential'])->toBeArray();
    });

    test('can generate optimization suggestions', function () {
        $this->monitor->startMonitoring();
        
        // Create a menu with items to generate some queries
        $menu = MenuItem::factory()->asMenu()->create();
        MenuItem::factory()->count(3)->forMenu($menu)->create();
        
        // Perform queries that might need optimization
        MenuItem::where('menu_id', $menu->id)->get();
        MenuItem::where('parent_id', $menu->id)->orderBy('position')->get();
        
        $stats = $this->monitor->stopMonitoring();
        $suggestions = $this->monitor->getMenuOptimizationSuggestions();
        
        expect($suggestions)->toBeArray();
    });

    test('can normalize SQL queries for pattern matching', function () {
        $this->monitor->startMonitoring();
        
        // Create similar queries with different values
        MenuItem::where('id', 1)->first();
        MenuItem::where('id', 2)->first();
        MenuItem::where('id', 3)->first();
        
        $stats = $this->monitor->stopMonitoring();
        
        // Should detect similar query patterns
        expect($stats['queries'])->toHaveCount(3);
    });

    test('can clear recorded queries', function () {
        $this->monitor->startMonitoring();
        MenuItem::factory()->asMenu()->create();
        $this->monitor->stopMonitoring();
        
        expect($this->monitor->getQueries())->toHaveCountGreaterThan(0);
        
        $this->monitor->clearQueries();
        
        expect($this->monitor->getQueries())->toHaveCount(0);
        expect($this->monitor->getSlowQueries())->toHaveCount(0);
    });

    test('can generate performance report', function () {
        $this->monitor->startMonitoring();
        
        // Generate various types of queries
        $menu = MenuItem::factory()->asMenu()->create(['name' => 'Report Test']);
        MenuItem::factory()->count(2)->forMenu($menu)->create();
        MenuItem::where('menu_id', $menu->id)->count();
        
        $this->monitor->stopMonitoring();
        $report = $this->monitor->generateReport();
        
        expect($report)->toBeString();
        expect($report)->toContain('Database Query Performance Report');
        expect($report)->toContain('Total Queries:');
        expect($report)->toContain('Total Time:');
    });

    test('can set and use custom slow query threshold', function () {
        $customThreshold = 50.0;
        $this->monitor->setSlowQueryThreshold($customThreshold);
        
        $this->monitor->startMonitoring();
        MenuItem::factory()->asMenu()->create();
        $stats = $this->monitor->stopMonitoring();
        
        // With a higher threshold, fewer queries should be considered slow
        expect($stats['slow_queries_count'])->toBe(0);
    });

    test('handles empty query sets gracefully', function () {
        $stats = $this->monitor->getStatistics();
        
        expect($stats['total_queries'])->toBe(0);
        expect($stats['total_time'])->toBe(0);
        expect($stats['average_time'])->toBe(0);
        expect($stats['slow_queries_count'])->toBe(0);
        expect($stats['queries'])->toBeArray();
        expect($stats['slow_queries'])->toBeArray();
    });

    test('can detect potential missing indexes', function () {
        $this->monitor->setSlowQueryThreshold(0.01); // Low threshold
        $this->monitor->startMonitoring();
        
        // Simulate a potentially slow query without WHERE clause
        DB::table('menu_items')->get();
        
        $stats = $this->monitor->stopMonitoring();
        $analysis = $stats['query_analysis'];
        
        expect($analysis)->toHaveKey('missing_indexes_potential');
    });
});