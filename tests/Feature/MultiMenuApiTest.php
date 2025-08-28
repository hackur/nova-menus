<?php

namespace Skylark\Menus\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\TestCase;
use Skylark\Menus\Models\MenuItem;
use Skylark\Menus\Services\ResourceLinkService;

class MultiMenuApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test application
        $this->app = $this->createApplication();
        $this->app->make('migrator')->run([__DIR__.'/../../database/migrations']);

        // Mock ResourceLinkService
        $this->app->singleton(ResourceLinkService::class, function () {
            return $this->createMock(ResourceLinkService::class);
        });
    }

    /** @test */
    public function it_returns_multiple_menus_successfully()
    {
        // Create multiple test menus
        $mainMenu = MenuItem::factory()->create([
            'name' => 'Main Navigation',
            'slug' => 'main-menu',
            'is_root' => true,
        ]);

        $footerMenu = MenuItem::factory()->create([
            'name' => 'Footer Navigation',
            'slug' => 'footer-menu',
            'is_root' => true,
        ]);

        // Add items to each menu
        MenuItem::factory()->create([
            'name' => 'Home',
            'custom_url' => '/',
            'parent_id' => $mainMenu->id,
        ]);

        MenuItem::factory()->create([
            'name' => 'Privacy',
            'custom_url' => '/privacy',
            'parent_id' => $footerMenu->id,
        ]);

        $response = $this->getJson('/api/menus?menus=main-menu,footer-menu');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'menus' => [
                    'main-menu' => [
                        'name',
                        'items' => [
                            '*' => [
                                'id',
                                'name',
                                'url',
                                'target',
                                'css_class',
                                'icon',
                                'children',
                            ],
                        ],
                    ],
                    'footer-menu' => [
                        'name',
                        'items',
                    ],
                ],
                'timestamp',
            ])
            ->assertJson([
                'menus' => [
                    'main-menu' => [
                        'name' => 'Main Navigation',
                    ],
                    'footer-menu' => [
                        'name' => 'Footer Navigation',
                    ],
                ],
            ]);

        $menuData = $response->json();
        $this->assertEquals('Home', $menuData['menus']['main-menu']['items'][0]['name']);
        $this->assertEquals('Privacy', $menuData['menus']['footer-menu']['items'][0]['name']);
    }

    /** @test */
    public function it_handles_mix_of_existing_and_non_existing_menus()
    {
        // Create only one menu
        $existingMenu = MenuItem::factory()->create([
            'name' => 'Existing Menu',
            'slug' => 'existing-menu',
            'is_root' => true,
        ]);

        MenuItem::factory()->create([
            'name' => 'Test Item',
            'custom_url' => '/test',
            'parent_id' => $existingMenu->id,
        ]);

        $response = $this->getJson('/api/menus?menus=existing-menu,non-existent-menu');

        $response->assertStatus(200)
            ->assertJson([
                'menus' => [
                    'existing-menu' => [
                        'name' => 'Existing Menu',
                    ],
                    'non-existent-menu' => [
                        'error' => 'Menu not found',
                        'message' => "Menu with slug 'non-existent-menu' does not exist",
                    ],
                ],
            ]);

        $menuData = $response->json();
        $this->assertArrayHasKey('items', $menuData['menus']['existing-menu']);
        $this->assertArrayNotHasKey('items', $menuData['menus']['non-existent-menu']);
        $this->assertArrayHasKey('error', $menuData['menus']['non-existent-menu']);
    }

    /** @test */
    public function it_returns_error_when_no_menus_specified()
    {
        $response = $this->getJson('/api/menus');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'No menus specified',
                'message' => 'Please provide comma-separated menu slugs via ?menus=slug1,slug2',
            ]);
    }

    /** @test */
    public function it_returns_error_when_empty_menus_parameter()
    {
        $response = $this->getJson('/api/menus?menus=');

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'No menus specified',
                'message' => 'Please provide comma-separated menu slugs via ?menus=slug1,slug2',
            ]);
    }

    /** @test */
    public function it_handles_single_menu_via_multi_menu_endpoint()
    {
        $menu = MenuItem::factory()->create([
            'name' => 'Single Menu',
            'slug' => 'single-menu',
            'is_root' => true,
        ]);

        MenuItem::factory()->create([
            'name' => 'Menu Item',
            'custom_url' => '/item',
            'parent_id' => $menu->id,
        ]);

        $response = $this->getJson('/api/menus?menus=single-menu');

        $response->assertStatus(200)
            ->assertJson([
                'menus' => [
                    'single-menu' => [
                        'name' => 'Single Menu',
                    ],
                ],
            ]);

        $menuData = $response->json();
        $this->assertCount(1, $menuData['menus']);
        $this->assertEquals('Menu Item', $menuData['menus']['single-menu']['items'][0]['name']);
    }

    /** @test */
    public function it_filters_temporal_visibility_across_multiple_menus()
    {
        $now = Carbon::now();

        // Create first menu with visible and hidden items
        $menu1 = MenuItem::factory()->create([
            'name' => 'Menu One',
            'slug' => 'menu-one',
            'is_root' => true,
        ]);

        $visibleItem1 = MenuItem::factory()->create([
            'name' => 'Visible in Menu 1',
            'custom_url' => '/visible1',
            'parent_id' => $menu1->id,
            'display_at' => $now->copy()->subHour(),
            'hide_at' => $now->copy()->addHour(),
        ]);

        $hiddenItem1 = MenuItem::factory()->create([
            'name' => 'Hidden in Menu 1',
            'custom_url' => '/hidden1',
            'parent_id' => $menu1->id,
            'display_at' => $now->copy()->addHour(),
            'hide_at' => $now->copy()->addDay(),
        ]);

        // Create second menu with different visibility rules
        $menu2 = MenuItem::factory()->create([
            'name' => 'Menu Two',
            'slug' => 'menu-two',
            'is_root' => true,
        ]);

        $visibleItem2 = MenuItem::factory()->create([
            'name' => 'Visible in Menu 2',
            'custom_url' => '/visible2',
            'parent_id' => $menu2->id,
            'display_at' => null,
            'hide_at' => null,
        ]);

        $expiredItem2 = MenuItem::factory()->create([
            'name' => 'Expired in Menu 2',
            'custom_url' => '/expired2',
            'parent_id' => $menu2->id,
            'display_at' => $now->copy()->subDay(),
            'hide_at' => $now->copy()->subHour(),
        ]);

        $response = $this->getJson('/api/menus?menus=menu-one,menu-two');

        $response->assertStatus(200);

        $menuData = $response->json();

        // Check Menu One - should only have visible item
        $menu1Items = collect($menuData['menus']['menu-one']['items'])->pluck('name');
        $this->assertContains('Visible in Menu 1', $menu1Items);
        $this->assertNotContains('Hidden in Menu 1', $menu1Items);

        // Check Menu Two - should only have always visible item
        $menu2Items = collect($menuData['menus']['menu-two']['items'])->pluck('name');
        $this->assertContains('Visible in Menu 2', $menu2Items);
        $this->assertNotContains('Expired in Menu 2', $menu2Items);
    }

    /** @test */
    public function it_handles_resource_filtering_across_multiple_menus()
    {
        // Create multiple menus
        $menu1 = MenuItem::factory()->create([
            'name' => 'Menu with Resources',
            'slug' => 'menu-resources',
            'is_root' => true,
        ]);

        $menu2 = MenuItem::factory()->create([
            'name' => 'Menu with Mixed Links',
            'slug' => 'menu-mixed',
            'is_root' => true,
        ]);

        // Add items with different resource states
        $validResourceItem = MenuItem::factory()->create([
            'name' => 'Valid Resource',
            'resource_type' => 'product',
            'resource_id' => 1,
            'resource_slug' => 'valid-product',
            'parent_id' => $menu1->id,
        ]);

        $deletedResourceItem = MenuItem::factory()->create([
            'name' => 'Deleted Resource',
            'resource_type' => 'product',
            'resource_id' => 2,
            'resource_slug' => 'deleted-product',
            'parent_id' => $menu1->id,
        ]);

        $customUrlItem = MenuItem::factory()->create([
            'name' => 'Custom URL',
            'custom_url' => '/custom',
            'parent_id' => $menu2->id,
        ]);

        // Mock ResourceLinkService
        $resourceService = $this->app->make(ResourceLinkService::class);
        $resourceService->method('getResource')->willReturnCallback(function ($type, $id) {
            if ($id == 1) {
                return ['id' => 1, 'name' => 'Valid Product', 'slug' => 'valid-product', 'is_deleted' => false];
            }
            if ($id == 2) {
                return ['id' => 2, 'name' => 'Deleted Product', 'slug' => 'deleted-product', 'is_deleted' => true];
            }

            return null;
        });

        $response = $this->getJson('/api/menus?menus=menu-resources,menu-mixed');

        $response->assertStatus(200);

        $menuData = $response->json();

        // Menu with resources should only have valid resource item
        $resourceMenuItems = collect($menuData['menus']['menu-resources']['items'])->pluck('name');
        $this->assertContains('Valid Resource', $resourceMenuItems);
        $this->assertNotContains('Deleted Resource', $resourceMenuItems);

        // Menu with mixed links should have custom URL item
        $mixedMenuItems = collect($menuData['menus']['menu-mixed']['items'])->pluck('name');
        $this->assertContains('Custom URL', $mixedMenuItems);
    }

    /** @test */
    public function it_handles_whitespace_in_menu_parameters()
    {
        $menu1 = MenuItem::factory()->create([
            'name' => 'Menu One',
            'slug' => 'menu-one',
            'is_root' => true,
        ]);

        $menu2 = MenuItem::factory()->create([
            'name' => 'Menu Two',
            'slug' => 'menu-two',
            'is_root' => true,
        ]);

        // Test with spaces around commas
        $response = $this->getJson('/api/menus?menus=menu-one, menu-two , menu-one');

        $response->assertStatus(200);

        $menuData = $response->json();

        $this->assertArrayHasKey('menu-one', $menuData['menus']);
        $this->assertArrayHasKey('menu-two', $menuData['menus']);

        // Verify both menus are returned correctly
        $this->assertEquals('Menu One', $menuData['menus']['menu-one']['name']);
        $this->assertEquals('Menu Two', $menuData['menus']['menu-two']['name']);
    }

    /** @test */
    public function it_handles_duplicate_menu_slugs_gracefully()
    {
        $menu = MenuItem::factory()->create([
            'name' => 'Duplicate Test Menu',
            'slug' => 'test-menu',
            'is_root' => true,
        ]);

        MenuItem::factory()->create([
            'name' => 'Test Item',
            'custom_url' => '/test',
            'parent_id' => $menu->id,
        ]);

        // Request same menu multiple times
        $response = $this->getJson('/api/menus?menus=test-menu,test-menu,test-menu');

        $response->assertStatus(200);

        $menuData = $response->json();

        // Should only return the menu once
        $this->assertCount(1, $menuData['menus']);
        $this->assertArrayHasKey('test-menu', $menuData['menus']);
        $this->assertEquals('Duplicate Test Menu', $menuData['menus']['test-menu']['name']);
        $this->assertCount(1, $menuData['menus']['test-menu']['items']);
    }

    /** @test */
    public function it_preserves_hierarchical_structure_across_multiple_menus()
    {
        // Create first menu with nested structure
        $menu1 = MenuItem::factory()->create([
            'name' => 'Nested Menu 1',
            'slug' => 'nested-menu-1',
            'is_root' => true,
        ]);

        $parent1 = MenuItem::factory()->create([
            'name' => 'Parent 1',
            'custom_url' => '/parent1',
            'parent_id' => $menu1->id,
        ]);

        $child1 = MenuItem::factory()->create([
            'name' => 'Child 1',
            'custom_url' => '/parent1/child1',
            'parent_id' => $parent1->id,
        ]);

        // Create second menu with different structure
        $menu2 = MenuItem::factory()->create([
            'name' => 'Nested Menu 2',
            'slug' => 'nested-menu-2',
            'is_root' => true,
        ]);

        $flatItem = MenuItem::factory()->create([
            'name' => 'Flat Item',
            'custom_url' => '/flat',
            'parent_id' => $menu2->id,
        ]);

        $response = $this->getJson('/api/menus?menus=nested-menu-1,nested-menu-2');

        $response->assertStatus(200);

        $menuData = $response->json();

        // Verify nested structure in first menu
        $menu1Data = $menuData['menus']['nested-menu-1'];
        $this->assertCount(1, $menu1Data['items']);
        $this->assertEquals('Parent 1', $menu1Data['items'][0]['name']);
        $this->assertCount(1, $menu1Data['items'][0]['children']);
        $this->assertEquals('Child 1', $menu1Data['items'][0]['children'][0]['name']);
        $this->assertEmpty($menu1Data['items'][0]['children'][0]['children']);

        // Verify flat structure in second menu
        $menu2Data = $menuData['menus']['nested-menu-2'];
        $this->assertCount(1, $menu2Data['items']);
        $this->assertEquals('Flat Item', $menu2Data['items'][0]['name']);
        $this->assertEmpty($menu2Data['items'][0]['children']);
    }

    /** @test */
    public function it_optimizes_database_queries_for_multiple_menus()
    {
        // Create multiple menus with items
        $menuSlugs = [];
        for ($i = 1; $i <= 5; $i++) {
            $menu = MenuItem::factory()->create([
                'name' => "Performance Menu {$i}",
                'slug' => "perf-menu-{$i}",
                'is_root' => true,
            ]);

            $menuSlugs[] = "perf-menu-{$i}";

            // Add multiple items to each menu
            for ($j = 1; $j <= 10; $j++) {
                MenuItem::factory()->create([
                    'name' => "Item {$j}",
                    'custom_url' => "/item{$j}",
                    'parent_id' => $menu->id,
                ]);
            }
        }

        // Enable query logging
        \DB::enableQueryLog();

        $response = $this->getJson('/api/menus?menus='.implode(',', $menuSlugs));

        $response->assertStatus(200);

        // Check that query count is reasonable (should be much less than individual menu requests)
        $queries = \DB::getQueryLog();
        $this->assertLessThan(15, count($queries)); // Should be optimized to minimal queries

        // Verify all menus were returned
        $menuData = $response->json();
        $this->assertCount(5, $menuData['menus']);

        foreach ($menuSlugs as $slug) {
            $this->assertArrayHasKey($slug, $menuData['menus']);
            $this->assertCount(10, $menuData['menus'][$slug]['items']);
        }
    }

    /** @test */
    public function it_returns_proper_timestamp_for_multiple_menus()
    {
        $menu1 = MenuItem::factory()->create([
            'name' => 'Timestamp Menu 1',
            'slug' => 'timestamp-menu-1',
            'is_root' => true,
        ]);

        $menu2 = MenuItem::factory()->create([
            'name' => 'Timestamp Menu 2',
            'slug' => 'timestamp-menu-2',
            'is_root' => true,
        ]);

        $response = $this->getJson('/api/menus?menus=timestamp-menu-1,timestamp-menu-2');

        $response->assertStatus(200);

        $menuData = $response->json();

        $this->assertArrayHasKey('timestamp', $menuData);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $menuData['timestamp']
        );

        // Timestamp should be close to current time
        $timestamp = Carbon::parse($menuData['timestamp']);
        $this->assertTrue($timestamp->diffInSeconds(now()) < 5);
    }

    /** @test */
    public function it_handles_all_menus_not_found()
    {
        $response = $this->getJson('/api/menus?menus=non-existent-1,non-existent-2');

        $response->assertStatus(200)
            ->assertJson([
                'menus' => [
                    'non-existent-1' => [
                        'error' => 'Menu not found',
                        'message' => "Menu with slug 'non-existent-1' does not exist",
                    ],
                    'non-existent-2' => [
                        'error' => 'Menu not found',
                        'message' => "Menu with slug 'non-existent-2' does not exist",
                    ],
                ],
            ]);
    }
}
