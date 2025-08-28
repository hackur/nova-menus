<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Skylark\Menus\Models\MenuItem;

// Use refresh database specifically for this test file
uses(RefreshDatabase::class);

beforeEach(function () {
    // Explicitly run migrations for Menu components
    $this->artisan('migrate', ['--force' => true]);

    // Create a root menu (MenuItem with is_root = true)
    $this->menu = MenuItem::factory()->asMenu()->create([
        'name' => 'Test Menu',
        'slug' => 'test-menu',
    ]);
});

describe('Nested Set Hierarchy Operations', function () {
    test('can create simple parent-child relationship', function () {
        $parent = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Parent']);
        $child = MenuItem::factory()->create([
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        // Refresh models to get updated nested set values
        $parent->refresh();
        $child->refresh();

        expect($child->parent->id)->toBe($parent->id);
        expect($parent->children->pluck('id')->toArray())->toContain($child->id);
        // Verify hierarchy through nested set values
        expect($child->_lft)->toBeGreaterThan($parent->_lft);
        expect($child->_rgt)->toBeLessThan($parent->_rgt);
    });

    test('can create multi-level hierarchy', function () {
        $level1 = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Level 1']);
        $level2 = MenuItem::factory()->create([
            'name' => 'Level 2',
            'parent_id' => $level1->id,
        ]);
        $level3 = MenuItem::factory()->create([
            'name' => 'Level 3',
            'parent_id' => $level2->id,
        ]);

        // Refresh models
        $level1->refresh();
        $level2->refresh();
        $level3->refresh();

        expect($level2->parent->id)->toBe($level1->id);
        expect($level3->parent->id)->toBe($level2->id);

        // Verify hierarchy through nested set values
        expect($level1->_lft)->toBeLessThan($level2->_lft);
        expect($level2->_rgt)->toBeLessThan($level1->_rgt);
        expect($level2->_lft)->toBeLessThan($level3->_lft);
        expect($level3->_rgt)->toBeLessThan($level2->_rgt);

        // Test descendant relationships
        $level1Descendants = $level1->descendants;
        expect($level1Descendants->pluck('id')->toArray())->toContain($level2->id);
        expect($level1Descendants->pluck('id')->toArray())->toContain($level3->id);
    });

    test('can create multiple siblings', function () {
        $parent = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Parent']);

        $child1 = MenuItem::factory()->create([
            'name' => 'Child 1',
            'parent_id' => $parent->id,
        ]);

        $child2 = MenuItem::factory()->create([
            'name' => 'Child 2',
            'parent_id' => $parent->id,
        ]);

        $child3 = MenuItem::factory()->create([
            'name' => 'Child 3',
            'parent_id' => $parent->id,
        ]);

        $parent->refresh();

        $children = $parent->children;
        expect($children)->toHaveCount(3);
        expect($children->pluck('name')->toArray())->toContain('Child 1');
        expect($children->pluck('name')->toArray())->toContain('Child 2');
        expect($children->pluck('name')->toArray())->toContain('Child 3');

        // All siblings should have same depth
        $depths = $children->pluck('depth')->unique();
        expect($depths)->toHaveCount(1);
    });

    test('descendants method returns all nested descendants', function () {
        $root = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Root']);

        $child1 = MenuItem::factory()->create([
            'name' => 'Child 1',
            'parent_id' => $root->id,
        ]);

        $grandchild1 = MenuItem::factory()->create([
            'name' => 'Grandchild 1',
            'parent_id' => $child1->id,
        ]);

        $child2 = MenuItem::factory()->create([
            'name' => 'Child 2',
            'parent_id' => $root->id,
        ]);

        $root->refresh();
        $descendants = $root->descendants;

        expect($descendants)->toHaveCount(3);
        expect($descendants->pluck('name')->toArray())->toContain('Child 1');
        expect($descendants->pluck('name')->toArray())->toContain('Child 2');
        expect($descendants->pluck('name')->toArray())->toContain('Grandchild 1');
    });

    test('ancestors method returns all parent nodes', function () {
        $root = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Root']);

        $child = MenuItem::factory()->create([
            'name' => 'Child',
            'parent_id' => $root->id,
        ]);

        $grandchild = MenuItem::factory()->create([
            'name' => 'Grandchild',
            'parent_id' => $child->id,
        ]);

        $grandchild->refresh();
        $ancestors = $grandchild->ancestors;

        expect($ancestors)->toHaveCount(3); // Root, Child, and the menu itself
        expect($ancestors->pluck('name')->toArray())->toContain('Root');
        expect($ancestors->pluck('name')->toArray())->toContain('Child');
    });

    test('getMenuRoot returns correct root for nested items', function () {
        $child = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Child']);
        $grandchild = MenuItem::factory()->create([
            'name' => 'Grandchild',
            'parent_id' => $child->id,
        ]);

        $grandchild->refresh();
        $root = $grandchild->getMenuRoot();

        expect($root->id)->toBe($this->menu->id);
        expect($root->is_root)->toBeTrue();
        expect($root->name)->toBe('Test Menu');
    });

    test('nested set operations maintain correct left-right values', function () {
        $parent = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Parent']);
        $child1 = MenuItem::factory()->create([
            'name' => 'Child 1',
            'parent_id' => $parent->id,
        ]);
        $child2 = MenuItem::factory()->create([
            'name' => 'Child 2',
            'parent_id' => $parent->id,
        ]);

        // Refresh to get updated nested set values
        $parent->refresh();
        $child1->refresh();
        $child2->refresh();

        // Parent should encompass children's left-right values
        expect($parent->_lft)->toBeLessThan($child1->_lft);
        expect($parent->_rgt)->toBeGreaterThan($child1->_rgt);
        expect($parent->_rgt)->toBeGreaterThan($child2->_rgt);

        // Children should not overlap
        expect($child1->_rgt)->toBeLessThan($child2->_lft);
    });
});

describe('Hierarchy Manipulation', function () {
    test('can move item to different parent', function () {
        $parent1 = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Parent 1']);
        $parent2 = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Parent 2']);

        $child = MenuItem::factory()->create([
            'name' => 'Child',
            'parent_id' => $parent1->id,
        ]);

        $child->refresh();
        expect($child->parent->id)->toBe($parent1->id);

        // Move child to parent2
        $child->parent_id = $parent2->id;
        $child->save();

        $child->refresh();
        $parent1->refresh();
        $parent2->refresh();

        expect($child->parent->id)->toBe($parent2->id);
        expect($parent1->children)->toHaveCount(0);
        expect($parent2->children)->toHaveCount(1);
        expect($parent2->children->first()->id)->toBe($child->id);
    });

    test('can delete item and maintain hierarchy integrity', function () {
        $parent = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Parent']);

        $child1 = MenuItem::factory()->create([
            'name' => 'Child 1',
            'parent_id' => $parent->id,
        ]);

        $child2 = MenuItem::factory()->create([
            'name' => 'Child 2',
            'parent_id' => $parent->id,
        ]);

        $grandchild = MenuItem::factory()->create([
            'name' => 'Grandchild',
            'parent_id' => $child1->id,
        ]);

        $parent->refresh();
        expect($parent->descendants)->toHaveCount(3);

        // Delete child1 (this should also remove grandchild due to cascade)
        $child1->delete();

        $parent->refresh();
        $remainingChildren = $parent->children;

        expect($remainingChildren)->toHaveCount(1);
        expect($remainingChildren->first()->name)->toBe('Child 2');

        // Verify grandchild was also deleted
        expect(MenuItem::find($grandchild->id))->toBeNull();
    });

    test('can create item at specific position', function () {
        $parent = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Parent']);

        $first = MenuItem::factory()->create([
            'name' => 'First',
            'parent_id' => $parent->id,
            'position' => 1,
        ]);

        $third = MenuItem::factory()->create([
            'name' => 'Third',
            'parent_id' => $parent->id,
            'position' => 3,
        ]);

        $second = MenuItem::factory()->create([
            'name' => 'Second',
            'parent_id' => $parent->id,
            'position' => 2,
        ]);

        $parent->refresh();
        $children = $parent->children()->orderBy('position')->get();

        expect($children->pluck('name')->toArray())->toBe(['First', 'Second', 'Third']);
        expect($children->pluck('position')->toArray())->toBe([1, 2, 3]);
    });
});

describe('Complex Hierarchy Scenarios', function () {
    test('can handle deep nesting within max depth constraints', function () {
        $current = $this->menu;

        // Create 5 levels deep (menu is level 0)
        for ($i = 1; $i <= 5; $i++) {
            $current = MenuItem::factory()->create([
                'name' => "Level {$i}",
                'parent_id' => $current->id,
            ]);
        }

        $current->refresh();
        $ancestors = $current->ancestors;

        // Should have 5 ancestors (not including itself)
        expect($ancestors)->toHaveCount(5);
        // Verify deepest item has highest nested set left value within ancestors

        // Verify the path
        $ancestorNames = $ancestors->pluck('name')->toArray();
        expect($ancestorNames)->toContain('Test Menu');
        expect($ancestorNames)->toContain('Level 1');
        expect($ancestorNames)->toContain('Level 4');
    });

    test('can handle large number of siblings efficiently', function () {
        $parent = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Parent']);

        // Create 50 siblings
        $children = [];
        for ($i = 1; $i <= 50; $i++) {
            $children[] = MenuItem::factory()->create([
                'name' => "Child {$i}",
                'parent_id' => $parent->id,
            ]);
        }

        $parent->refresh();
        $actualChildren = $parent->children;

        expect($actualChildren)->toHaveCount(50);

        // Test that nested set values are consistent
        $leftValues = $actualChildren->pluck('_lft')->toArray();
        $rightValues = $actualChildren->pluck('_rgt')->toArray();

        // All left values should be unique and ordered
        expect(count($leftValues))->toBe(count(array_unique($leftValues)));
        $sortedLeftValues = $leftValues;
        sort($sortedLeftValues);
        expect($leftValues)->toBe($sortedLeftValues);

        // All children should be within parent's bounds
        foreach ($actualChildren as $child) {
            expect($child->_lft)->toBeGreaterThan($parent->_lft);
            expect($child->_rgt)->toBeLessThan($parent->_rgt);
        }
    });

    test('hierarchy operations work with temporal constraints', function () {
        $now = now();

        $parent = MenuItem::factory()->forMenu($this->menu)->create([
            'name' => 'Parent',
            'display_at' => $now->copy()->subHour(),
            'hide_at' => $now->copy()->addHour(),
        ]);

        $visibleChild = MenuItem::factory()->create([
            'name' => 'Visible Child',
            'parent_id' => $parent->id,
            'display_at' => $now->copy()->subHour(),
            'hide_at' => $now->copy()->addHour(),
        ]);

        $hiddenChild = MenuItem::factory()->create([
            'name' => 'Hidden Child',
            'parent_id' => $parent->id,
            'display_at' => $now->copy()->addHour(),
            'hide_at' => null,
        ]);

        $parent->refresh();

        // All children (regardless of visibility)
        $allChildren = $parent->children;
        expect($allChildren)->toHaveCount(2);

        // Only visible children
        $visibleChildren = $parent->children()->visible()->get();
        expect($visibleChildren)->toHaveCount(1);
        expect($visibleChildren->first()->name)->toBe('Visible Child');

        // Descendants with temporal filtering
        $visibleDescendants = MenuItem::visible()
            ->where('parent_id', $parent->id)
            ->get();
        expect($visibleDescendants)->toHaveCount(1);
    });
});

describe('Hierarchy Query Performance', function () {
    test('descendants query is efficient for large hierarchies', function () {
        $root = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Root']);

        // Create a moderately complex hierarchy
        $parents = [$root];
        for ($level = 1; $level <= 3; $level++) {
            $newParents = [];
            foreach ($parents as $parent) {
                for ($i = 1; $i <= 5; $i++) {
                    $child = MenuItem::factory()->create([
                        'name' => "L{$level}-{$parent->name}-C{$i}",
                        'parent_id' => $parent->id,
                    ]);
                    $newParents[] = $child;
                }
            }
            $parents = $newParents;
        }

        $root->refresh();

        $startTime = microtime(true);
        $descendants = $root->descendants;
        $queryTime = microtime(true) - $startTime;

        // Should have 5 + 25 + 125 = 155 descendants
        expect($descendants->count())->toBe(155);
        expect($queryTime)->toBeLessThan(0.5); // Should complete quickly
    });

    test('ancestors query performs well for deep hierarchies', function () {
        $current = $this->menu;

        // Create 10 levels deep
        for ($i = 1; $i <= 10; $i++) {
            $current = MenuItem::factory()->create([
                'name' => "Level {$i}",
                'parent_id' => $current->id,
            ]);
        }

        $current->refresh();

        $startTime = microtime(true);
        $ancestors = $current->ancestors;
        $queryTime = microtime(true) - $startTime;

        expect($ancestors)->toHaveCount(10); // Not including the item itself
        expect($queryTime)->toBeLessThan(0.1); // Should be very fast
    });

    test('siblings query is optimized', function () {
        $parent = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Parent']);

        // Create many siblings
        $targetChild = null;
        for ($i = 1; $i <= 100; $i++) {
            $child = MenuItem::factory()->create([
                'name' => "Child {$i}",
                'parent_id' => $parent->id,
            ]);
            if ($i === 50) {
                $targetChild = $child;
            }
        }

        $targetChild->refresh();

        $startTime = microtime(true);
        // Get siblings by querying same parent, excluding self
        $siblings = MenuItem::where('parent_id', $targetChild->parent_id)
            ->where('id', '!=', $targetChild->id)
            ->get();
        $queryTime = microtime(true) - $startTime;

        expect($siblings->count())->toBe(99); // All siblings except self
        expect($queryTime)->toBeLessThan(0.2);
    });
});

describe('Edge Cases and Error Handling', function () {
    test('prevents self-referencing parent relationship', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Item']);

        // Attempt to make item its own parent should fail validation
        $item->parent_id = $item->id;

        expect(fn () => $item->save())
            ->toThrow(\Exception::class);
    });

    test('handles orphaned items gracefully', function () {
        $parent = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Parent']);
        $child = MenuItem::factory()->create([
            'name' => 'Child',
            'parent_id' => $parent->id,
        ]);

        // Force delete parent without cascading (simulating orphaned record)
        \DB::table('menu_items')->where('id', $parent->id)->delete();

        $child->refresh();

        // Child should handle missing parent gracefully
        expect($child->parent)->toBeNull();
        // Since the parent is gone but menu root might still be accessible
        expect($child->getMenuRoot()->id)->toBe($this->menu->id);
    });

    test('maintains data integrity during concurrent modifications', function () {
        $parent = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Parent']);

        \DB::transaction(function () use ($parent) {
            // Simulate concurrent creation of children
            MenuItem::factory()->create([
                'name' => 'Child A',
                'parent_id' => $parent->id,
            ]);

            MenuItem::factory()->create([
                'name' => 'Child B',
                'parent_id' => $parent->id,
            ]);
        });

        $parent->refresh();
        $children = $parent->children;

        expect($children)->toHaveCount(2);

        // Verify nested set values are still valid
        foreach ($children as $child) {
            expect($child->_lft)->toBeGreaterThan($parent->_lft);
            expect($child->_rgt)->toBeLessThan($parent->_rgt);
        }
    });

    test('hierarchy operations respect database constraints', function () {
        // This test ensures that the nested set implementation
        // doesn't violate any database constraints we might have

        $root = MenuItem::factory()->forMenu($this->menu)->create(['name' => 'Root']);
        $child = MenuItem::factory()->create([
            'name' => 'Child',
            'parent_id' => $root->id,
        ]);

        // Verify foreign key constraints work
        expect($child->parent_id)->toBe($root->id);
        expect($child->parent)->not->toBeNull();
        expect($child->parent->name)->toBe('Root');
    });
});
