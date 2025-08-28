<?php

namespace Skylark\Menus\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\TestCase;
use Skylark\Menus\Models\MenuItem;
use Skylark\Menus\Services\ResourceLinkService;

class MenuApiTest extends TestCase
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
    public function it_returns_single_menu_with_hierarchical_structure()
    {
        // Create root menu
        $menu = MenuItem::factory()->create([
            'name' => 'Main Menu',
            'slug' => 'main-menu',
            'is_root' => true,
        ]);

        // Create child items
        $parent = MenuItem::factory()->create([
            'name' => 'Products',
            'custom_url' => '/products',
            'parent_id' => $menu->id,
        ]);

        $child = MenuItem::factory()->create([
            'name' => 'Electronics',
            'custom_url' => '/products/electronics',
            'parent_id' => $parent->id,
        ]);

        $response = $this->getJson('/api/menus/main-menu');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'slug',
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
                'timestamp',
            ])
            ->assertJson([
                'slug' => 'main-menu',
                'name' => 'Main Menu',
            ]);

        // Check hierarchical structure
        $menuData = $response->json();
        $this->assertCount(1, $menuData['items']); // Only parent item at root level
        $this->assertEquals('Products', $menuData['items'][0]['name']);
        $this->assertCount(1, $menuData['items'][0]['children']); // Child item
        $this->assertEquals('Electronics', $menuData['items'][0]['children'][0]['name']);
    }

    /** @test */
    public function it_returns_404_for_non_existent_menu()
    {
        $response = $this->getJson('/api/menus/non-existent-menu');

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Menu not found',
                'message' => "Menu with slug 'non-existent-menu' does not exist",
            ]);
    }

    /** @test */
    public function it_filters_out_temporally_hidden_items()
    {
        $now = Carbon::now();

        // Create root menu
        $menu = MenuItem::factory()->create([
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'is_root' => true,
        ]);

        // Create visible item
        $visibleItem = MenuItem::factory()->create([
            'name' => 'Visible Item',
            'custom_url' => '/visible',
            'parent_id' => $menu->id,
            'display_at' => $now->copy()->subHour(),
            'hide_at' => $now->copy()->addHour(),
        ]);

        // Create hidden item (future)
        $hiddenItem = MenuItem::factory()->create([
            'name' => 'Hidden Item',
            'custom_url' => '/hidden',
            'parent_id' => $menu->id,
            'display_at' => $now->copy()->addHour(),
            'hide_at' => $now->copy()->addDay(),
        ]);

        $response = $this->getJson('/api/menus/test-menu');

        $response->assertStatus(200);

        $menuData = $response->json();
        $itemNames = collect($menuData['items'])->pluck('name');

        $this->assertContains('Visible Item', $itemNames);
        $this->assertNotContains('Hidden Item', $itemNames);
    }

    /** @test */
    public function it_filters_out_items_with_soft_deleted_resources()
    {
        // Create root menu
        $menu = MenuItem::factory()->create([
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'is_root' => true,
        ]);

        // Create item with valid resource
        $validItem = MenuItem::factory()->create([
            'name' => 'Valid Resource Item',
            'resource_type' => 'product',
            'resource_id' => 1,
            'resource_slug' => 'test-product',
            'parent_id' => $menu->id,
        ]);

        // Create item with soft-deleted resource
        $deletedItem = MenuItem::factory()->create([
            'name' => 'Deleted Resource Item',
            'resource_type' => 'product',
            'resource_id' => 2,
            'resource_slug' => 'deleted-product',
            'parent_id' => $menu->id,
        ]);

        // Create item with custom URL (always valid)
        $customUrlItem = MenuItem::factory()->create([
            'name' => 'Custom URL Item',
            'custom_url' => '/custom-page',
            'parent_id' => $menu->id,
        ]);

        // Mock ResourceLinkService to simulate resource states
        $resourceService = $this->app->make(ResourceLinkService::class);
        $resourceService->method('getResource')->willReturnCallback(function ($type, $id) {
            if ($id == 1) {
                return ['id' => 1, 'name' => 'Test Product', 'slug' => 'test-product', 'is_deleted' => false];
            }
            if ($id == 2) {
                return ['id' => 2, 'name' => 'Deleted Product', 'slug' => 'deleted-product', 'is_deleted' => true];
            }

            return null;
        });

        $response = $this->getJson('/api/menus/test-menu');

        $response->assertStatus(200);

        $menuData = $response->json();
        $itemNames = collect($menuData['items'])->pluck('name');

        $this->assertContains('Valid Resource Item', $itemNames);
        $this->assertNotContains('Deleted Resource Item', $itemNames);
        $this->assertContains('Custom URL Item', $itemNames);
    }

    /** @test */
    public function it_generates_urls_correctly()
    {
        // Create root menu
        $menu = MenuItem::factory()->create([
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'is_root' => true,
        ]);

        // Create item with custom URL
        $customUrlItem = MenuItem::factory()->create([
            'name' => 'Custom URL Item',
            'custom_url' => '/custom-page',
            'parent_id' => $menu->id,
        ]);

        // Create item with resource
        $resourceItem = MenuItem::factory()->create([
            'name' => 'Resource Item',
            'resource_type' => 'product',
            'resource_slug' => 'test-product',
            'parent_id' => $menu->id,
        ]);

        // Mock ResourceLinkService for URL generation
        $resourceService = $this->app->make(ResourceLinkService::class);
        $resourceService->method('generateUrl')
            ->with('product', 'test-product')
            ->willReturn('/products/test-product');

        $response = $this->getJson('/api/menus/test-menu');

        $response->assertStatus(200);

        $menuData = $response->json();
        $items = collect($menuData['items'])->keyBy('name');

        $this->assertEquals('/custom-page', $items['Custom URL Item']['url']);
        $this->assertEquals('/products/test-product', $items['Resource Item']['url']);
    }

    /** @test */
    public function it_includes_proper_menu_item_properties()
    {
        // Create root menu
        $menu = MenuItem::factory()->create([
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'is_root' => true,
        ]);

        // Create item with all properties
        $item = MenuItem::factory()->create([
            'name' => 'Full Featured Item',
            'custom_url' => '/featured',
            'target' => '_blank',
            'css_class' => 'featured-link',
            'icon' => 'star',
            'parent_id' => $menu->id,
        ]);

        $response = $this->getJson('/api/menus/test-menu');

        $response->assertStatus(200);

        $menuData = $response->json();
        $item = $menuData['items'][0];

        $this->assertEquals('Full Featured Item', $item['name']);
        $this->assertEquals('/featured', $item['url']);
        $this->assertEquals('_blank', $item['target']);
        $this->assertEquals('featured-link', $item['css_class']);
        $this->assertEquals('star', $item['icon']);
        $this->assertIsArray($item['children']);
    }

    /** @test */
    public function it_handles_deep_nested_hierarchies()
    {
        // Create root menu
        $menu = MenuItem::factory()->create([
            'name' => 'Deep Menu',
            'slug' => 'deep-menu',
            'is_root' => true,
        ]);

        // Create 3-level hierarchy
        $level1 = MenuItem::factory()->create([
            'name' => 'Level 1',
            'custom_url' => '/level1',
            'parent_id' => $menu->id,
        ]);

        $level2 = MenuItem::factory()->create([
            'name' => 'Level 2',
            'custom_url' => '/level2',
            'parent_id' => $level1->id,
        ]);

        $level3 = MenuItem::factory()->create([
            'name' => 'Level 3',
            'custom_url' => '/level3',
            'parent_id' => $level2->id,
        ]);

        $response = $this->getJson('/api/menus/deep-menu');

        $response->assertStatus(200);

        $menuData = $response->json();

        // Navigate through nested structure
        $this->assertCount(1, $menuData['items']);
        $this->assertEquals('Level 1', $menuData['items'][0]['name']);

        $this->assertCount(1, $menuData['items'][0]['children']);
        $this->assertEquals('Level 2', $menuData['items'][0]['children'][0]['name']);

        $this->assertCount(1, $menuData['items'][0]['children'][0]['children']);
        $this->assertEquals('Level 3', $menuData['items'][0]['children'][0]['children'][0]['name']);

        // Deepest level should have no children
        $this->assertEmpty($menuData['items'][0]['children'][0]['children'][0]['children']);
    }

    /** @test */
    public function it_respects_rate_limiting()
    {
        // Create test menu
        $menu = MenuItem::factory()->create([
            'name' => 'Rate Limited Menu',
            'slug' => 'rate-test',
            'is_root' => true,
        ]);

        // Make requests up to the rate limit (60 per minute)
        // This is a conceptual test - actual implementation depends on rate limiting setup
        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson('/api/menus/rate-test');
            $response->assertStatus(200);
        }

        // 61st request should be rate limited
        $response = $this->getJson('/api/menus/rate-test');
        $response->assertStatus(429);
    }

    /** @test */
    public function it_returns_timestamps_in_iso_format()
    {
        // Create test menu
        $menu = MenuItem::factory()->create([
            'name' => 'Timestamp Menu',
            'slug' => 'timestamp-test',
            'is_root' => true,
        ]);

        $response = $this->getJson('/api/menus/timestamp-test');

        $response->assertStatus(200);

        $menuData = $response->json();

        $this->assertArrayHasKey('timestamp', $menuData);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $menuData['timestamp']
        );

        // Verify it's a valid Carbon instance when parsed
        $timestamp = Carbon::parse($menuData['timestamp']);
        $this->assertInstanceOf(Carbon::class, $timestamp);
    }

    /** @test */
    public function it_handles_empty_menus_gracefully()
    {
        // Create empty menu
        $menu = MenuItem::factory()->create([
            'name' => 'Empty Menu',
            'slug' => 'empty-menu',
            'is_root' => true,
        ]);

        $response = $this->getJson('/api/menus/empty-menu');

        $response->assertStatus(200)
            ->assertJson([
                'slug' => 'empty-menu',
                'name' => 'Empty Menu',
                'items' => [],
            ]);
    }

    /** @test */
    public function it_validates_slug_format()
    {
        // Test with valid slug formats
        $validSlugs = ['menu', 'main-menu', 'footer_menu', 'menu123'];

        foreach ($validSlugs as $slug) {
            $response = $this->getJson("/api/menus/{$slug}");
            // Should get 404 for non-existent menu, not 400 for invalid format
            $response->assertStatus(404);
        }

        // Test with potentially invalid slug formats (implementation dependent)
        $invalidSlugs = ['menu/with/slashes', 'menu with spaces'];

        foreach ($invalidSlugs as $slug) {
            $response = $this->getJson("/api/menus/{$slug}");
            // Could be 400 (bad format) or 404 (not found) depending on route constraints
            $this->assertContains($response->status(), [400, 404]);
        }
    }
}
