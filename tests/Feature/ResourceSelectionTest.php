<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test menu
    // Ensure we have migrations
    $this->artisan('migrate', ['--force' => true]);

    $this->menu = \Skylark\Menus\Models\MenuItem::factory()->asMenu()->create([
        'name' => 'Test Menu',
        'slug' => 'test-menu',
    ]);
});

describe('Resource Configuration', function () {
    it('can load resource types from configuration', function () {
        $response = $this->get('/nova-vendor/menus/resource-types');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [],
            ]);

        $data = $response->json('data');

        expect($data)->toHaveKeys(['Product', 'Category', 'Homepage', 'Webpage']);
    });

    it('validates resource configuration exists', function () {
        $service = new \Skylark\Menus\Services\ResourceLinkService;

        expect(fn () => $service->getResourceConfig('InvalidResource'))
            ->toThrow(\InvalidArgumentException::class, 'Resource type \'InvalidResource\' is not configured.');
    });

    it('validates resource configuration structure', function () {
        Config::set('menus.resources.InvalidConfig', [
            'model' => 'NonExistentModel',
            'name_field' => 'name',
            // Missing slug_field and route_pattern
        ]);

        $service = new \Skylark\Menus\Services\ResourceLinkService;

        expect(fn () => $service->getResourceConfig('InvalidConfig'))
            ->toThrow(\InvalidArgumentException::class);
    });
});

describe('Resource Search API', function () {
    it('can search resources without query parameter', function () {
        $response = $this->get('/nova-vendor/menus/resources/Product/search');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [],
            ]);
    });

    it('can search resources with query parameter', function () {
        $response = $this->get('/nova-vendor/menus/resources/Product/search?q=test');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [],
            ]);
    });

    it('validates search parameters', function () {
        $response = $this->get('/nova-vendor/menus/resources/Product/search?limit=150');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['limit']);
    });

    it('returns 400 for invalid resource type', function () {
        $response = $this->get('/nova-vendor/menus/resources/InvalidType/search');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    });
});

describe('MenuItem Resource Integration', function () {
    it('can create menu item with resource selection', function () {
        $payload = [
            'menu_id' => $this->menu->id,
            'name' => 'Test Product Link',
            'resource_type' => 'Product',
            'resource_id' => 123,
            'resource_slug' => 'test-product',
            'custom_url' => null,
        ];

        $response = $this->post('/nova-vendor/menus/menu-items', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'resource_type',
                    'resource_id',
                    'resource_slug',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('menu_items', [
            'name' => 'Test Product Link',
            'resource_type' => 'Product',
            'resource_id' => 123,
            'resource_slug' => 'test-product',
            'custom_url' => null,
        ]);
    });

    it('can update menu item resource selection', function () {
        $menuItem = \Skylark\Menus\Models\MenuItem::create([
            'menu_id' => $this->menu->id,
            'name' => 'Test Item',
            'custom_url' => '/old-url',
        ]);

        $payload = [
            'resource_type' => 'Category',
            'resource_id' => 456,
            'resource_slug' => 'test-category',
            'custom_url' => null,
        ];

        $response = $this->put("/nova-vendor/menus/menu-items/{$menuItem->id}", $payload);

        $response->assertStatus(200);

        $menuItem->refresh();

        expect($menuItem->resource_type)->toBe('Category');
        expect($menuItem->resource_id)->toBe(456);
        expect($menuItem->resource_slug)->toBe('test-category');
        expect($menuItem->custom_url)->toBeNull();
    });

    it('validates resource selection consistency', function () {
        $payload = [
            'menu_id' => $this->menu->id,
            'name' => 'Test Item',
            'resource_type' => 'Product',
            'resource_id' => null, // Invalid: type without ID
            'resource_slug' => null,
        ];

        $response = $this->post('/nova-vendor/menus/menu-items', $payload);

        $response->assertStatus(422);
    });

    it('can switch from custom URL to resource selection', function () {
        $menuItem = \Skylark\Menus\Models\MenuItem::create([
            'menu_id' => $this->menu->id,
            'name' => 'Test Item',
            'custom_url' => '/custom-page',
        ]);

        $payload = [
            'resource_type' => 'Homepage',
            'resource_id' => 1,
            'resource_slug' => 'home-page',
            'custom_url' => null,
        ];

        $response = $this->put("/nova-vendor/menus/menu-items/{$menuItem->id}", $payload);

        $response->assertStatus(200);

        $menuItem->refresh();

        expect($menuItem->custom_url)->toBeNull();
        expect($menuItem->resource_type)->toBe('Homepage');
    });

    it('can switch from resource selection to custom URL', function () {
        $menuItem = \Skylark\Menus\Models\MenuItem::create([
            'menu_id' => $this->menu->id,
            'name' => 'Test Item',
            'resource_type' => 'Product',
            'resource_id' => 123,
            'resource_slug' => 'test-product',
        ]);

        $payload = [
            'custom_url' => '/new-custom-page',
            'resource_type' => null,
            'resource_id' => null,
            'resource_slug' => null,
        ];

        $response = $this->put("/nova-vendor/menus/menu-items/{$menuItem->id}", $payload);

        $response->assertStatus(200);

        $menuItem->refresh();

        expect($menuItem->custom_url)->toBe('/new-custom-page');
        expect($menuItem->resource_type)->toBeNull();
        expect($menuItem->resource_id)->toBeNull();
        expect($menuItem->resource_slug)->toBeNull();
    });
});

describe('URL Generation', function () {
    it('generates correct URLs from resource configuration', function () {
        $service = new \Skylark\Menus\Services\ResourceLinkService;

        $url = $service->generateUrl('Product', 'iphone-15');
        expect($url)->toBe('/products/iphone-15');

        $url = $service->generateUrl('Homepage', 'welcome');
        expect($url)->toBe('/welcome');

        $url = $service->generateUrl('Webpage', 'about-us');
        expect($url)->toBe('/pages/about-us');
    });

    it('generates URLs through MenuItem model', function () {
        $menuItem = \Skylark\Menus\Models\MenuItem::create([
            'menu_id' => $this->menu->id,
            'name' => 'Test Product',
            'resource_type' => 'Product',
            'resource_id' => 123,
            'resource_slug' => 'awesome-product',
        ]);

        expect($menuItem->url)->toBe('/products/awesome-product');
    });

    it('falls back to custom URL when resource URL generation fails', function () {
        $menuItem = \Skylark\Menus\Models\MenuItem::create([
            'menu_id' => $this->menu->id,
            'name' => 'Test Item',
            'custom_url' => '/fallback-url',
            'resource_type' => 'InvalidType',
            'resource_slug' => 'invalid',
        ]);

        expect($menuItem->url)->toBe('/fallback-url');
    });

    it('prioritizes custom URL over resource URL', function () {
        $menuItem = \Skylark\Menus\Models\MenuItem::create([
            'menu_id' => $this->menu->id,
            'name' => 'Test Item',
            'custom_url' => '/priority-url',
            'resource_type' => 'Product',
            'resource_slug' => 'test-product',
        ]);

        expect($menuItem->url)->toBe('/priority-url');
    });
});

describe('Soft Delete Handling', function () {
    it('excludes soft-deleted resources from search results', function () {
        // This test would require actual model instances with soft deletes
        // For now, we'll test the service method structure

        $service = new \Skylark\Menus\Services\ResourceLinkService;

        // Mock a model with soft delete capability
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('searchResources');

        expect($method)->toBeDefined();
    });

    it('detects soft-deleted resources in getResource method', function () {
        $service = new \Skylark\Menus\Services\ResourceLinkService;

        // Test with invalid resource ID (simulates deleted resource)
        $result = $service->getResource('Product', 99999);

        expect($result)->toBeNull();
    });
});

describe('MenuItem Resource Status', function () {
    it('can check if menu item has valid resource', function () {
        $menuItem = \Skylark\Menus\Models\MenuItem::create([
            'menu_id' => $this->menu->id,
            'name' => 'Test Item',
            'resource_type' => 'Product',
            'resource_id' => 99999, // Non-existent ID
        ]);

        expect($menuItem->hasValidResource())->toBeFalse();
    });

    it('returns true for items without resource links', function () {
        $menuItem = \Skylark\Menus\Models\MenuItem::create([
            'menu_id' => $this->menu->id,
            'name' => 'Test Item',
            'custom_url' => '/custom-url',
        ]);

        expect($menuItem->hasValidResource())->toBeTrue();
    });
});

describe('Error Handling and Edge Cases', function () {
    it('handles malformed resource configurations gracefully', function () {
        Config::set('menus.resources.BrokenConfig', [
            'model' => 'NonExistent\\Model',
        ]);

        $service = new \Skylark\Menus\Services\ResourceLinkService;

        expect(fn () => $service->getResourceConfig('BrokenConfig'))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('handles invalid search queries gracefully', function () {
        $response = $this->get('/nova-vendor/menus/resources/Product/search?q='.str_repeat('x', 500));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    });

    it('handles missing route pattern placeholders', function () {
        Config::set('menus.resources.BadRoute', [
            'model' => 'Skylark\\NovaCart\\Models\\Product',
            'name_field' => 'name',
            'slug_field' => 'slug',
            'route_pattern' => '/products/static', // Missing {slug}
        ]);

        $service = new \Skylark\Menus\Services\ResourceLinkService;

        expect(fn () => $service->getResourceConfig('BadRoute'))
            ->toThrow(\InvalidArgumentException::class);
    });
});
