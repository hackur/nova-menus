<?php

namespace Skylark\Menus\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Skylark\Menus\Models\Menu;
use Skylark\Menus\Models\MenuItem;
use Tests\TestCase;

class NestedSetOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Menu $menu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->menu = Menu::factory()->create(['max_depth' => 5]);
    }

    /** @test */
    public function it_uses_nested_set_with_depth_for_validation()
    {
        // Create a hierarchy to test depth calculation
        $level0 = MenuItem::factory()->create(['menu_id' => $this->menu->id]);
        $level1 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $level0->id]);
        $level2 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $level1->id]);

        // Query with depth information
        $itemsWithDepth = MenuItem::where('menu_id', $this->menu->id)
            ->withDepth()
            ->get()
            ->keyBy('id');

        // Verify depth calculations
        $this->assertEquals(0, $itemsWithDepth[$level0->id]->depth);
        $this->assertEquals(1, $itemsWithDepth[$level1->id]->depth);
        $this->assertEquals(2, $itemsWithDepth[$level2->id]->depth);
    }

    /** @test */
    public function it_validates_depth_limits_using_nested_set_depth()
    {
        // Create items at maximum allowed depth
        $current = null;
        $items = [];

        for ($i = 0; $i < $this->menu->max_depth; $i++) {
            $item = MenuItem::factory()->create([
                'menu_id' => $this->menu->id,
                'parent_id' => $current?->id,
                'name' => "Level {$i} Item",
            ]);
            $items[] = $item;
            $current = $item;
        }

        // Try to add one more level (should fail)
        $extraItem = MenuItem::factory()->create(['menu_id' => $this->menu->id]);

        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => [
                ['id' => $extraItem->id, 'position' => 0, 'parent_id' => $current->id],
            ],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', "Moving item would exceed maximum depth limit of {$this->menu->max_depth}");
    }

    /** @test */
    public function it_maintains_nested_set_values_after_reorder()
    {
        // Create a simple tree structure
        $root = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'name' => 'Root']);
        $child1 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $root->id, 'name' => 'Child 1']);
        $child2 = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $root->id, 'name' => 'Child 2']);
        $grandchild = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $child1->id, 'name' => 'Grandchild']);

        // Reorder: move child2 to be first child
        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => [
                ['id' => $child2->id, 'position' => 0, 'parent_id' => $root->id],
                ['id' => $child1->id, 'position' => 1, 'parent_id' => $root->id],
            ],
        ]);

        $response->assertStatus(200);

        // Refresh models to get updated nested set values
        $root->refresh();
        $child1->refresh();
        $child2->refresh();
        $grandchild->refresh();

        // Verify nested set integrity rules
        // Rule 1: Root should have the widest range
        $this->assertTrue($root->_lft < $child1->_lft);
        $this->assertTrue($root->_lft < $child2->_lft);
        $this->assertTrue($root->_rgt > $child1->_rgt);
        $this->assertTrue($root->_rgt > $child2->_rgt);

        // Rule 2: Child ranges should not overlap
        $this->assertTrue(
            ($child1->_rgt < $child2->_lft) || ($child2->_rgt < $child1->_lft),
            'Child node ranges should not overlap'
        );

        // Rule 3: Grandchild should be within child1's range
        $this->assertTrue($child1->_lft < $grandchild->_lft);
        $this->assertTrue($child1->_rgt > $grandchild->_rgt);
    }

    /** @test */
    public function it_handles_moving_subtrees_correctly()
    {
        // Create two separate trees
        $tree1Root = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'name' => 'Tree 1']);
        $tree1Child = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $tree1Root->id, 'name' => 'Tree 1 Child']);
        $tree1Grandchild = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'parent_id' => $tree1Child->id, 'name' => 'Tree 1 Grandchild']);

        $tree2Root = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'name' => 'Tree 2']);

        // Move entire tree1Child subtree under tree2Root
        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => [
                ['id' => $tree1Child->id, 'position' => 0, 'parent_id' => $tree2Root->id],
            ],
        ]);

        $response->assertStatus(200);

        // Refresh models
        $tree1Root->refresh();
        $tree1Child->refresh();
        $tree1Grandchild->refresh();
        $tree2Root->refresh();

        // Verify the move was successful
        $this->assertEquals($tree2Root->id, $tree1Child->parent_id);
        $this->assertEquals($tree1Child->id, $tree1Grandchild->parent_id); // Grandchild should still be under child

        // Verify nested set integrity after subtree move
        $this->assertTrue($tree2Root->_lft < $tree1Child->_lft);
        $this->assertTrue($tree2Root->_rgt > $tree1Child->_rgt);
        $this->assertTrue($tree1Child->_lft < $tree1Grandchild->_lft);
        $this->assertTrue($tree1Child->_rgt > $tree1Grandchild->_rgt);

        // Verify tree1Root now has no children
        $this->assertEquals(0, $tree1Root->children()->count());

        // Verify tree2Root now has the moved subtree
        $this->assertEquals(1, $tree2Root->children()->count());
        $this->assertEquals($tree1Child->id, $tree2Root->children()->first()->id);
    }

    /** @test */
    public function it_validates_depth_with_complex_hierarchies()
    {
        // Create a scenario where depth calculation is complex
        $root1 = MenuItem::factory()->create(['menu_id' => $this->menu->id]);
        $root2 = MenuItem::factory()->create(['menu_id' => $this->menu->id]);

        // Build a deep hierarchy under root1 (max depth - 1)
        $current = $root1;
        for ($i = 1; $i < $this->menu->max_depth - 1; $i++) {
            $current = MenuItem::factory()->create([
                'menu_id' => $this->menu->id,
                'parent_id' => $current->id,
                'name' => "Depth {$i}",
            ]);
        }

        // Now try to move root2 under the deepest item (should succeed since it's exactly at max depth)
        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => [
                ['id' => $root2->id, 'position' => 0, 'parent_id' => $current->id],
            ],
        ]);

        $response->assertStatus(200);

        // Verify the move succeeded
        $root2->refresh();
        $this->assertEquals($current->id, $root2->parent_id);

        // Now create another item and try to move it under root2 (should fail)
        $extraItem = MenuItem::factory()->create(['menu_id' => $this->menu->id]);

        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => [
                ['id' => $extraItem->id, 'position' => 0, 'parent_id' => $root2->id],
            ],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function it_properly_rebuilds_nested_set_after_complex_operations()
    {
        // Create multiple items at different levels
        $items = [];
        for ($i = 0; $i < 5; $i++) {
            $items[] = MenuItem::factory()->create(['menu_id' => $this->menu->id, 'name' => "Item {$i}"]);
        }

        // Create some parent-child relationships
        $items[1]->parent_id = $items[0]->id;
        $items[1]->save();

        $items[2]->parent_id = $items[0]->id;
        $items[2]->save();

        $items[3]->parent_id = $items[1]->id;
        $items[3]->save();

        // Perform a complex reorder: move items[3] to be under items[4]
        $response = $this->putJson("/nova-vendor/menus/menus/{$this->menu->id}/items/reorder", [
            'items' => [
                ['id' => $items[3]->id, 'position' => 0, 'parent_id' => $items[4]->id],
            ],
        ]);

        $response->assertStatus(200);

        // Verify that all nested set values are consistent
        $allItems = MenuItem::where('menu_id', $this->menu->id)->orderBy('_lft')->get();

        foreach ($allItems as $item) {
            // Each item should have valid _lft and _rgt values
            $this->assertNotNull($item->_lft);
            $this->assertNotNull($item->_rgt);
            $this->assertTrue($item->_lft < $item->_rgt, "Item {$item->id} has invalid nested set values");

            // If item has a parent, it should be within parent's range
            if ($item->parent_id) {
                $parent = MenuItem::find($item->parent_id);
                $this->assertTrue($parent->_lft < $item->_lft, "Item {$item->id} is not within parent's left range");
                $this->assertTrue($parent->_rgt > $item->_rgt, "Item {$item->id} is not within parent's right range");
            }
        }
    }
}
