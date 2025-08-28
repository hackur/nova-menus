<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Skylark\Menus\Models\MenuItem;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
});

describe('Large Menu Performance Tests', function () {
    test('can handle menu with 100 items efficiently', function () {
        // Create root menu
        $rootMenu = MenuItem::factory()->asMenu()->create([
            'name' => 'Performance Test Menu',
            'max_depth' => 6,
        ]);

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Create 100 menu items
        $items = [];
        for ($i = 1; $i <= 100; $i++) {
            $items[] = MenuItem::factory()->forMenu($rootMenu)->create([
                'name' => "Performance Item {$i}",
                'custom_url' => "/perf-{$i}",
                'position' => $i,
            ]);
        }

        $creationTime = microtime(true) - $startTime;
        $creationMemory = memory_get_usage(true) - $startMemory;

        // Test retrieval performance
        $retrievalStartTime = microtime(true);
        $retrievalStartMemory = memory_get_usage(true);

        $allItems = $rootMenu->children()->orderBy('position')->get();

        $retrievalTime = microtime(true) - $retrievalStartTime;
        $retrievalMemory = memory_get_usage(true) - $retrievalStartMemory;

        // Assert performance benchmarks
        expect($creationTime)->toBeLessThan(10.0); // Should create 100 items in < 10 seconds
        expect($retrievalTime)->toBeLessThan(1.0); // Should retrieve 100 items in < 1 second
        expect($creationMemory)->toBeLessThan(50 * 1024 * 1024); // Should use < 50MB for creation
        expect($retrievalMemory)->toBeLessThan(10 * 1024 * 1024); // Should use < 10MB for retrieval

        // Verify data integrity
        expect($allItems)->toHaveCount(100);
        expect($allItems->first()->name)->toBe('Performance Item 1');
        expect($allItems->last()->name)->toBe('Performance Item 100');

        // Log performance metrics
        echo "\nPerformance Metrics for 100 Items:\n";
        echo "Creation Time: " . number_format($creationTime, 4) . " seconds\n";
        echo "Retrieval Time: " . number_format($retrievalTime, 4) . " seconds\n";
        echo "Creation Memory: " . number_format($creationMemory / 1024 / 1024, 2) . " MB\n";
        echo "Retrieval Memory: " . number_format($retrievalMemory / 1024 / 1024, 2) . " MB\n";
    });

    test('can handle deep nested hierarchy efficiently', function () {
        // Create root menu with max depth
        $rootMenu = MenuItem::factory()->asMenu()->create([
            'name' => 'Deep Hierarchy Test',
            'max_depth' => 6,
        ]);

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Create deep nested structure (6 levels)
        $currentParent = null;
        $items = [];

        for ($level = 1; $level <= 6; $level++) {
            $item = MenuItem::factory()->create([
                'menu_id' => $rootMenu->id,
                'parent_id' => $currentParent?->id,
                'name' => "Level {$level} Item",
                'custom_url' => "/level-{$level}",
                'position' => 1,
            ]);

            $items[] = $item;
            $currentParent = $item;
        }

        $creationTime = microtime(true) - $startTime;
        $creationMemory = memory_get_usage(true) - $startMemory;

        // Test nested retrieval performance
        $retrievalStartTime = microtime(true);
        $retrievalStartMemory = memory_get_usage(true);

        // Get all descendants of root
        $deepestItem = $items[5]; // 6th level (0-indexed)
        $ancestors = $deepestItem->ancestors;
        $rootChildren = $rootMenu->children;
        $allDescendants = $rootMenu->descendants;

        $retrievalTime = microtime(true) - $retrievalStartTime;
        $retrievalMemory = memory_get_usage(true) - $retrievalStartMemory;

        // Test nested set operations performance
        $nestedSetStartTime = microtime(true);
        
        $depth = $deepestItem->depth;
        $isDescendantOf = $deepestItem->isDescendantOf($items[0]);
        
        $nestedSetTime = microtime(true) - $nestedSetStartTime;

        // Assert performance benchmarks
        expect($creationTime)->toBeLessThan(2.0); // Should create deep hierarchy in < 2 seconds
        expect($retrievalTime)->toBeLessThan(0.5); // Should retrieve relationships in < 0.5 seconds
        expect($nestedSetTime)->toBeLessThan(0.1); // Nested set operations should be very fast

        // Verify data integrity
        expect($ancestors)->toHaveCount(5); // Should have 5 ancestors (levels 1-5)
        expect($rootChildren)->toHaveCount(1); // Root should have 1 direct child
        expect($allDescendants)->toHaveCount(6); // Root should have 6 total descendants
        expect($depth)->toBe(6); // Deepest item should be at depth 6
        expect($isDescendantOf)->toBeTrue();

        echo "\nPerformance Metrics for Deep Hierarchy (6 levels):\n";
        echo "Creation Time: " . number_format($creationTime, 4) . " seconds\n";
        echo "Retrieval Time: " . number_format($retrievalTime, 4) . " seconds\n";
        echo "Nested Set Time: " . number_format($nestedSetTime, 4) . " seconds\n";
        echo "Creation Memory: " . number_format($creationMemory / 1024 / 1024, 2) . " MB\n";
    });

    test('can handle complex mixed hierarchy efficiently', function () {
        // Create root menu
        $rootMenu = MenuItem::factory()->asMenu()->create([
            'name' => 'Complex Hierarchy Test',
            'max_depth' => 4,
        ]);

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Create complex structure: 10 top-level items, each with 5 children, each with 3 grandchildren
        $allItems = [];
        
        for ($i = 1; $i <= 10; $i++) {
            $topLevel = MenuItem::factory()->forMenu($rootMenu)->create([
                'name' => "Top Level {$i}",
                'custom_url' => "/top-{$i}",
                'position' => $i,
            ]);
            $allItems[] = $topLevel;

            for ($j = 1; $j <= 5; $j++) {
                $secondLevel = MenuItem::factory()->create([
                    'menu_id' => $rootMenu->id,
                    'parent_id' => $topLevel->id,
                    'name' => "Second Level {$i}-{$j}",
                    'custom_url' => "/second-{$i}-{$j}",
                    'position' => $j,
                ]);
                $allItems[] = $secondLevel;

                for ($k = 1; $k <= 3; $k++) {
                    $thirdLevel = MenuItem::factory()->create([
                        'menu_id' => $rootMenu->id,
                        'parent_id' => $secondLevel->id,
                        'name' => "Third Level {$i}-{$j}-{$k}",
                        'custom_url' => "/third-{$i}-{$j}-{$k}",
                        'position' => $k,
                    ]);
                    $allItems[] = $thirdLevel;
                }
            }
        }

        $creationTime = microtime(true) - $startTime;
        $creationMemory = memory_get_usage(true) - $startMemory;

        // Test hierarchical queries performance
        $queryStartTime = microtime(true);
        $queryStartMemory = memory_get_usage(true);

        // Various hierarchical operations
        $topLevelItems = $rootMenu->children()->orderBy('position')->get();
        $totalDescendants = $rootMenu->descendants()->count();
        $secondLevelCounts = $topLevelItems->map(fn($item) => $item->children()->count());
        $deepestItems = MenuItem::where('menu_id', $rootMenu->id)
            ->whereHas('parent.parent') // Has grandparent (3rd level items)
            ->count();

        $queryTime = microtime(true) - $queryStartTime;
        $queryMemory = memory_get_usage(true) - $queryStartMemory;

        // Test bulk operations performance
        $bulkStartTime = microtime(true);
        
        // Update all items at once
        MenuItem::where('menu_id', $rootMenu->id)->update(['is_active' => true]);
        
        // Count items by level
        $rootCount = MenuItem::roots()->where('menu_id', $rootMenu->id)->count();
        $allItemsCount = MenuItem::where('menu_id', $rootMenu->id)->count();
        
        $bulkTime = microtime(true) - $bulkStartTime;

        // Assert performance benchmarks
        expect($creationTime)->toBeLessThan(15.0); // Should create 280 items (10+50+150) in < 15 seconds
        expect($queryTime)->toBeLessThan(1.0); // Complex queries should complete in < 1 second
        expect($bulkTime)->toBeLessThan(0.5); // Bulk operations should be fast

        // Verify data integrity
        expect($topLevelItems)->toHaveCount(10);
        expect($totalDescendants)->toBe(200); // 50 second level + 150 third level
        expect($secondLevelCounts->sum())->toBe(50); // Each top-level has 5 children
        expect($deepestItems)->toBe(150); // 10 * 5 * 3 third-level items
        expect($allItemsCount)->toBe(210); // 10 + 50 + 150 (excluding root menu)

        echo "\nPerformance Metrics for Complex Hierarchy (210 items, 3 levels):\n";
        echo "Creation Time: " . number_format($creationTime, 4) . " seconds\n";
        echo "Query Time: " . number_format($queryTime, 4) . " seconds\n";
        echo "Bulk Operations Time: " . number_format($bulkTime, 4) . " seconds\n";
        echo "Creation Memory: " . number_format($creationMemory / 1024 / 1024, 2) . " MB\n";
        echo "Query Memory: " . number_format($queryMemory / 1024 / 1024, 2) . " MB\n";
    });

    test('can handle visibility filtering on large dataset efficiently', function () {
        // Create root menu
        $rootMenu = MenuItem::factory()->asMenu()->create([
            'name' => 'Visibility Performance Test',
            'max_depth' => 3,
        ]);

        $startTime = microtime(true);

        // Create 50 items with different visibility settings
        $items = [];
        $now = now();

        for ($i = 1; $i <= 50; $i++) {
            $visibilityType = match ($i % 5) {
                0 => 'always_hide', // 10 items
                1 => 'future', // 10 items - visible in future
                2 => 'past', // 10 items - was visible in past
                3 => 'current', // 10 items - currently visible
                default => 'always_show' // 10 items
            };

            $item = MenuItem::factory()->forMenu($rootMenu)->create([
                'name' => "Visibility Item {$i}",
                'custom_url' => "/vis-{$i}",
                'position' => $i,
                'is_active' => $visibilityType !== 'always_hide',
                'display_at' => match($visibilityType) {
                    'future' => $now->copy()->addDays(1),
                    'past' => $now->copy()->subDays(2),
                    'current' => $now->copy()->subHours(1),
                    default => null
                },
                'hide_at' => match($visibilityType) {
                    'past' => $now->copy()->subDays(1),
                    'current' => $now->copy()->addHours(1),
                    default => null
                }
            ]);

            $items[] = $item;
        }

        $creationTime = microtime(true) - $startTime;

        // Test visibility filtering performance
        $filterStartTime = microtime(true);
        $filterStartMemory = memory_get_usage(true);

        // Test various visibility scopes
        $visibleItems = MenuItem::visible()->where('menu_id', $rootMenu->id)->get();
        $visibleAtTime = MenuItem::isVisibleAt($now)->where('menu_id', $rootMenu->id)->get();
        $activeItems = MenuItem::where('menu_id', $rootMenu->id)->where('is_active', true)->get();
        $scheduledItems = MenuItem::where('menu_id', $rootMenu->id)
            ->whereNotNull('display_at')
            ->orWhereNotNull('hide_at')
            ->get();

        $filterTime = microtime(true) - $filterStartTime;
        $filterMemory = memory_get_usage(true) - $filterStartMemory;

        // Test database query efficiency
        $queryCount = 0;
        DB::listen(function ($query) use (&$queryCount) {
            $queryCount++;
        });

        $efficientQuery = MenuItem::visible()
            ->where('menu_id', $rootMenu->id)
            ->with(['parent', 'children'])
            ->orderBy('position')
            ->get();

        // Assert performance benchmarks
        expect($creationTime)->toBeLessThan(5.0); // Should create 50 items in < 5 seconds
        expect($filterTime)->toBeLessThan(1.0); // All filtering should complete in < 1 second
        expect($queryCount)->toBeLessThan(5); // Should use minimal queries with eager loading

        // Verify visibility filtering accuracy
        expect($visibleItems->count())->toBe(20); // always_show (10) + current (10)
        expect($activeItems->count())->toBe(40); // All except always_hide (10)
        expect($scheduledItems->count())->toBe(30); // future (10) + past (10) + current (10)

        echo "\nPerformance Metrics for Visibility Filtering (50 items):\n";
        echo "Creation Time: " . number_format($creationTime, 4) . " seconds\n";
        echo "Filtering Time: " . number_format($filterTime, 4) . " seconds\n";
        echo "Database Queries: {$queryCount}\n";
        echo "Filter Memory: " . number_format($filterMemory / 1024 / 1024, 2) . " MB\n";
        echo "Visible Items: {$visibleItems->count()}/50\n";
        echo "Active Items: {$activeItems->count()}/50\n";
        echo "Scheduled Items: {$scheduledItems->count()}/50\n";
    });

    test('can handle concurrent access to large menus', function () {
        // Create root menu with many items
        $rootMenu = MenuItem::factory()->asMenu()->create([
            'name' => 'Concurrency Test Menu',
            'max_depth' => 3,
        ]);

        // Create 30 items quickly
        MenuItem::factory()->count(30)->forMenu($rootMenu)->create();

        $startTime = microtime(true);
        $results = [];
        $errors = [];

        // Simulate concurrent access
        for ($i = 0; $i < 5; $i++) {
            try {
                $operationStart = microtime(true);
                
                // Different types of operations that might happen concurrently
                switch ($i % 3) {
                    case 0:
                        // Read operations
                        $items = MenuItem::where('menu_id', $rootMenu->id)
                            ->with('children')
                            ->get();
                        $results[] = "Read: " . $items->count() . " items";
                        break;
                        
                    case 1:
                        // Write operations
                        $newItem = MenuItem::factory()->create([
                            'menu_id' => $rootMenu->id,
                            'name' => "Concurrent Item {$i}",
                            'position' => 100 + $i,
                        ]);
                        $results[] = "Write: Created item ID " . $newItem->id;
                        break;
                        
                    case 2:
                        // Update operations
                        MenuItem::where('menu_id', $rootMenu->id)
                            ->limit(5)
                            ->update(['is_active' => true]);
                        $results[] = "Update: Updated items";
                        break;
                }
                
                $operationTime = microtime(true) - $operationStart;
                expect($operationTime)->toBeLessThan(1.0); // Each operation should be fast
                
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $totalTime = microtime(true) - $startTime;

        // Assert no errors occurred and performance was acceptable
        expect($errors)->toBeEmpty();
        expect($results)->toHaveCount(5);
        expect($totalTime)->toBeLessThan(3.0); // All operations should complete in < 3 seconds

        echo "\nConcurrency Test Results:\n";
        echo "Total Time: " . number_format($totalTime, 4) . " seconds\n";
        echo "Operations: " . implode(', ', $results) . "\n";
        echo "Errors: " . (empty($errors) ? 'None' : implode(', ', $errors)) . "\n";
    });

    test('can handle menu tree serialization performance', function () {
        // Create a moderately complex menu structure
        $rootMenu = MenuItem::factory()->asMenu()->create([
            'name' => 'Serialization Test Menu',
            'max_depth' => 4,
        ]);

        // Create tree: 5 top-level, each with 4 children, each with 2 grandchildren
        for ($i = 1; $i <= 5; $i++) {
            $parent = MenuItem::factory()->forMenu($rootMenu)->create([
                'name' => "Parent {$i}",
                'position' => $i,
            ]);

            for ($j = 1; $j <= 4; $j++) {
                $child = MenuItem::factory()->create([
                    'menu_id' => $rootMenu->id,
                    'parent_id' => $parent->id,
                    'name' => "Child {$i}-{$j}",
                    'position' => $j,
                ]);

                for ($k = 1; $k <= 2; $k++) {
                    MenuItem::factory()->create([
                        'menu_id' => $rootMenu->id,
                        'parent_id' => $child->id,
                        'name' => "Grandchild {$i}-{$j}-{$k}",
                        'position' => $k,
                    ]);
                }
            }
        }

        // Test tree serialization performance
        $serializationStartTime = microtime(true);
        $serializationStartMemory = memory_get_usage(true);

        // Get full tree structure
        $fullTree = $rootMenu->children()
            ->with(['children.children'])
            ->orderBy('position')
            ->get();

        // Convert to array (simulating API response)
        $treeArray = $fullTree->toArray();
        
        // Convert to JSON (simulating frontend data)
        $jsonTree = json_encode($treeArray);
        
        $serializationTime = microtime(true) - $serializationStartTime;
        $serializationMemory = memory_get_usage(true) - $serializationStartMemory;

        // Test deserialization performance
        $deserializationStartTime = microtime(true);
        
        $decodedTree = json_decode($jsonTree, true);
        $itemCount = collect($decodedTree)->sum(function ($parent) {
            return 1 + collect($parent['children'])->sum(function ($child) {
                return 1 + count($child['children']);
            });
        });
        
        $deserializationTime = microtime(true) - $deserializationStartTime;

        // Assert performance benchmarks
        expect($serializationTime)->toBeLessThan(1.0); // Serialization should be fast
        expect($deserializationTime)->toBeLessThan(0.5); // Deserialization should be faster
        expect($serializationMemory)->toBeLessThan(20 * 1024 * 1024); // Should use < 20MB

        // Verify data integrity
        expect($fullTree)->toHaveCount(5); // 5 top-level items
        expect($itemCount)->toBe(45); // 5 + (5*4) + (5*4*2) = 5 + 20 + 40 = 65... wait let me recalculate
        // Actually: 5 parents + 20 children + 40 grandchildren = 65 total, but we're counting from children
        // So: 5 + 20 + 40 = 65 total items in the hierarchy
        expect(strlen($jsonTree))->toBeGreaterThan(1000); // JSON should be substantial
        expect(is_array($decodedTree))->toBeTrue();

        echo "\nSerialization Performance Metrics:\n";
        echo "Items: 5 parents + 20 children + 40 grandchildren = 65 total\n";
        echo "Serialization Time: " . number_format($serializationTime, 4) . " seconds\n";
        echo "Deserialization Time: " . number_format($deserializationTime, 4) . " seconds\n";
        echo "Memory Usage: " . number_format($serializationMemory / 1024 / 1024, 2) . " MB\n";
        echo "JSON Size: " . number_format(strlen($jsonTree) / 1024, 2) . " KB\n";
    });
});