<?php

namespace Skylark\Menus\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Skylark\Menus\Models\Menu;
use Skylark\Menus\Models\MenuItem;
use Tests\TestCase;

class DragDropIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menu = Menu::factory()->create(['max_depth' => 4]);
    }

    /** @test - 1.7-INT-001: Vue 3 + Nova 5.7 compatibility verification */
    public function it_handles_api_requests_for_drag_operations()
    {
        $item1 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'position' => 0]);
        $item2 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'position' => 1]);

        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => [
                ['id' => $item2->id, 'position' => 0, 'parent_id' => null],
                ['id' => $item1->id, 'position' => 1, 'parent_id' => null],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /** @test - 1.7-INT-003: Menu item reorder API call execution */
    public function it_executes_reorder_api_calls_successfully()
    {
        $parent = MenuItem::factory()->create(['menu_id' => $this->menu->id]);
        $child1 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $parent->id]);
        $child2 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $parent->id]);
        $child3 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $parent->id]);

        // Reorder children: child3, child1, child2
        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => [
                ['id' => $child3->id, 'position' => 0, 'parent_id' => $parent->id],
                ['id' => $child1->id, 'position' => 1, 'parent_id' => $parent->id],
                ['id' => $child2->id, 'position' => 2, 'parent_id' => $parent->id],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify the reorder took effect
        $this->assertEquals(0, MenuItem::find($child3->id)->position);
        $this->assertEquals(1, MenuItem::find($child1->id)->position);
        $this->assertEquals(2, MenuItem::find($child2->id)->position);
    }

    /** @test - 1.7-INT-004: Drag state management across components */
    public function it_maintains_state_consistency_during_drag_operations()
    {
        $items = collect();
        for ($i = 0; $i < 5; $i++) {
            $items->push(MenuItem::factory()->create([
                'menu_id' => $this->menu->id,
                'position' => $i,
            ]));
        }

        // Simulate complex reordering: reverse the order
        $reorderedItems = $items->reverse()->values()->map(function ($item, $index) {
            return [
                'id' => $item->id,
                'position' => $index,
                'parent_id' => null,
            ];
        })->toArray();

        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => $reorderedItems,
        ]);

        $response->assertStatus(200);

        // Verify all items have correct positions
        foreach ($reorderedItems as $expectedItem) {
            $actualItem = MenuItem::find($expectedItem['id']);
            $this->assertEquals($expectedItem['position'], $actualItem->position);
        }
    }

    /** @test - 1.7-INT-005: Depth validation during drag preview */
    public function it_validates_depth_during_drag_operations()
    {
        // Create a hierarchy at max depth
        $level0 = MenuItem::factory()->create(['menu_id' => $this->menu->id]);
        $level1 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $level0->id]);
        $level2 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $level1->id]);
        $level3 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $level2->id]);

        // Try to create level 4 (should be prevented)
        $orphanItem = MenuItem::factory()->create(['menu_id' => $this->menu->id]);

        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => [
                ['id' => $orphanItem->id, 'position' => 0, 'parent_id' => $level3->id],
            ],
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => "Moving item would exceed maximum depth limit of {$this->menu->max_depth}",
            ]);
    }

    /** @test - 1.7-INT-006: Drop prevention for invalid depths */
    public function it_prevents_drops_that_exceed_depth_limits()
    {
        // Create items at different levels
        $root1 = MenuItem::factory()->create(['menu_id' => $this->menu->id]);
        $root2 = MenuItem::factory()->create(['menu_id' => $this->menu->id]);

        // Create a deep hierarchy under root1
        $current = $root1;
        for ($i = 1; $i < $this->menu->max_depth; $i++) {
            $current = MenuItem::factory()->create([
                'menu_id' => $this->menu->id,
                'parent_id' => $current->id,
            ]);
        }

        // Try to move root2 under the deepest item (would exceed limit)
        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => [
                ['id' => $root2->id, 'position' => 0, 'parent_id' => $current->id],
            ],
        ]);

        $response->assertStatus(400);

        // Verify the item wasn't moved
        $root2->refresh();
        $this->assertNull($root2->parent_id);
    }

    /** @test - 1.7-INT-007: API endpoint transaction execution */
    public function it_handles_transactions_properly_during_reorder()
    {
        $items = MenuItem::factory()->count(3)->create(['menu_id' => $this->menu->id]);

        // Create a scenario that should succeed
        $validReorder = $items->map(function ($item, $index) {
            return [
                'id' => $item->id,
                'position' => 2 - $index, // Reverse order
                'parent_id' => null,
            ];
        })->toArray();

        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => $validReorder,
        ]);

        $response->assertStatus(200);

        // Verify all changes were committed
        foreach ($validReorder as $expected) {
            $actual = MenuItem::find($expected['id']);
            $this->assertEquals($expected['position'], $actual->position);
        }
    }

    /** @test - 1.7-INT-008: Rollback mechanism on failed updates */
    public function it_rolls_back_changes_on_failure()
    {
        $items = MenuItem::factory()->count(2)->create(['menu_id' => $this->menu->id]);
        $otherMenu = Menu::factory()->create();
        $otherMenuItem = MenuItem::factory()->create(['menu_id' => $otherMenu->id]);

        // Store original positions
        $originalPositions = $items->pluck('position', 'id')->toArray();

        // Create a batch with one valid and one invalid item
        $mixedReorder = [
            ['id' => $items[0]->id, 'position' => 5, 'parent_id' => null], // Valid
            ['id' => $otherMenuItem->id, 'position' => 0, 'parent_id' => null], // Invalid - wrong menu
        ];

        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => $mixedReorder,
        ]);

        $response->assertStatus(400);

        // Verify original positions are maintained (transaction rolled back)
        foreach ($items as $item) {
            $item->refresh();
            $this->assertEquals($originalPositions[$item->id], $item->position);
        }
    }

    /** @test - 1.7-INT-009: Concurrent drag operation handling */
    public function it_handles_concurrent_operations_safely()
    {
        $items = MenuItem::factory()->count(3)->create(['menu_id' => $this->menu->id]);

        // Simulate two "concurrent" reorder requests
        $reorder1 = [
            ['id' => $items[1]->id, 'position' => 0, 'parent_id' => null],
            ['id' => $items[0]->id, 'position' => 1, 'parent_id' => null],
            ['id' => $items[2]->id, 'position' => 2, 'parent_id' => null],
        ];

        $reorder2 = [
            ['id' => $items[2]->id, 'position' => 0, 'parent_id' => null],
            ['id' => $items[1]->id, 'position' => 1, 'parent_id' => null],
            ['id' => $items[0]->id, 'position' => 2, 'parent_id' => null],
        ];

        // Execute first reorder
        $response1 = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => $reorder1,
        ]);

        $response1->assertStatus(200);

        // Execute second reorder
        $response2 = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => $reorder2,
        ]);

        $response2->assertStatus(200);

        // Verify the last operation won (eventual consistency)
        $this->assertEquals(0, MenuItem::find($items[2]->id)->position);
        $this->assertEquals(1, MenuItem::find($items[1]->id)->position);
        $this->assertEquals(2, MenuItem::find($items[0]->id)->position);
    }

    /** @test - 1.7-INT-012: Nested set integrity after complex moves */
    public function it_maintains_nested_set_integrity_after_complex_operations()
    {
        // Create a complex hierarchy
        $root1 = MenuItem::factory()->create(['menu_id' => $this->menu->id]);
        $child1_1 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $root1->id]);
        $child1_2 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $root1->id]);

        $root2 = MenuItem::factory()->create(['menu_id' => $this->menu->id]);
        $child2_1 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $root2->id]);

        // Move child1_2 to be under root2
        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => [
                ['id' => $child1_2->id, 'position' => 1, 'parent_id' => $root2->id],
            ],
        ]);

        $response->assertStatus(200);

        // Refresh all models
        $models = [$root1, $child1_1, $child1_2, $root2, $child2_1];
        foreach ($models as $model) {
            $model->refresh();
        }

        // Verify nested set integrity
        $this->assertNotNull($root1->_lft);
        $this->assertNotNull($root1->_rgt);
        $this->assertNotNull($root2->_lft);
        $this->assertNotNull($root2->_rgt);

        // Verify parent-child relationships
        $this->assertEquals($root2->id, $child1_2->parent_id);
        $this->assertEquals(2, $root2->children()->count()); // Should now have 2 children
        $this->assertEquals(1, $root1->children()->count()); // Should now have 1 child
    }

    /** @test - 1.7-INT-013: Nova dashboard navigation unaffected by drags */
    public function it_does_not_affect_other_nova_functionality()
    {
        // This is more of a placeholder test since we can't easily test Nova dashboard navigation
        // in a unit test context. In a real scenario, this would be an E2E test.

        $items = MenuItem::factory()->count(2)->create(['menu_id' => $this->menu->id]);

        // Perform a reorder operation
        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => [
                ['id' => $items[1]->id, 'position' => 0, 'parent_id' => null],
                ['id' => $items[0]->id, 'position' => 1, 'parent_id' => null],
            ],
        ]);

        $response->assertStatus(200);

        // Verify that basic API functionality still works (proxy for Nova functionality)
        $menuResponse = $this->getJson("/nova-vendor/menus/menus/{$this->menu->id}");
        $menuResponse->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
