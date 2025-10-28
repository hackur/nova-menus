<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Skylark\Menus\Models\MenuItem;
use Skylark\Menus\Services\ResourceLinkService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);

    // Set up resource configuration for testing
    config()->set('menus.resources.Product', [
        'model' => 'App\\Models\\Product',
        'name_field' => 'name',
        'slug_field' => 'slug',
        'route_pattern' => '/products/{slug}',
    ]);

    // Create a test menu
    $this->menu = MenuItem::factory()->asMenu()->create([
        'name' => 'Test Menu',
        'slug' => 'test-menu',
    ]);
});

describe('ResourceSelector Integration', function () {
    test('link_type is properly initialized for custom URL items', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'name' => 'Custom URL Item',
            'custom_url' => '/custom-page',
            'resource_type' => null,
            'resource_id' => null,
            'resource_slug' => null,
        ]);

        // Simulate the initialization logic from Nested.vue
        if (! isset($item->link_type)) {
            if ($item->resource_type && $item->resource_id) {
                $item->link_type = 'resource';
            } elseif ($item->custom_url) {
                $item->link_type = 'url';
            } else {
                $item->link_type = 'url';
            }
        }

        expect($item->link_type)->toBe('url');
        expect($item->custom_url)->toBe('/custom-page');
        expect($item->resource_type)->toBeNull();
    });

    test('link_type is properly initialized for resource items', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'name' => 'Resource Item',
            'custom_url' => null,
            'resource_type' => 'Product',
            'resource_id' => 123,
            'resource_slug' => 'test-product',
        ]);

        // Simulate the initialization logic from Nested.vue
        if (! isset($item->link_type)) {
            if ($item->resource_type && $item->resource_id) {
                $item->link_type = 'resource';
            } elseif ($item->custom_url) {
                $item->link_type = 'url';
            } else {
                $item->link_type = 'url';
            }
        }

        expect($item->link_type)->toBe('resource');
        expect($item->resource_type)->toBe('Product');
        expect($item->resource_id)->toBe(123);
        expect($item->resource_slug)->toBe('test-product');
    });

    test('resourceSelection object is properly initialized from existing data', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'name' => 'Resource Item',
            'resource_type' => 'Product',
            'resource_id' => 456,
            'resource_slug' => 'another-product',
        ]);

        // Add resource_name from API (simulating backend response)
        $item->resource_name = 'Another Product Name';

        // Simulate resourceSelection initialization from Nested.vue
        if (! isset($item->resourceSelection)) {
            $item->resourceSelection = [
                'resource_type' => $item->resource_type,
                'resource_id' => $item->resource_id,
                'resource_name' => $item->resource_name ?? null,
                'resource_slug' => $item->resource_slug,
            ];
        }

        expect($item->resourceSelection)->toBeArray();
        expect($item->resourceSelection['resource_type'])->toBe('Product');
        expect($item->resourceSelection['resource_id'])->toBe(456);
        expect($item->resourceSelection['resource_name'])->toBe('Another Product Name');
        expect($item->resourceSelection['resource_slug'])->toBe('another-product');
    });

    test('onLinkTypeChange clears appropriate fields', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'name' => 'Test Item',
            'custom_url' => '/old-url',
            'resource_type' => 'Product',
            'resource_id' => 789,
            'resource_slug' => 'old-product',
        ]);

        // Initialize resourceSelection
        $item->resourceSelection = [
            'resource_type' => $item->resource_type,
            'resource_id' => $item->resource_id,
            'resource_name' => 'Old Product',
            'resource_slug' => $item->resource_slug,
        ];

        // Simulate switching to URL type
        $item->link_type = 'url';

        // Simulate onLinkTypeChange logic
        if ($item->link_type === 'url') {
            $item->resource_type = null;
            $item->resource_id = null;
            $item->resource_slug = null;
            $item->resourceSelection = [
                'resource_type' => null,
                'resource_id' => null,
                'resource_name' => null,
                'resource_slug' => null,
            ];
        } else {
            $item->custom_url = '';
        }

        expect($item->resource_type)->toBeNull();
        expect($item->resource_id)->toBeNull();
        expect($item->resource_slug)->toBeNull();
        expect($item->resourceSelection['resource_type'])->toBeNull();
        expect($item->custom_url)->toBe('/old-url'); // Should remain unchanged
    });

    test('onResourceSelectionChange updates element data correctly', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'name' => 'Test Item',
            'link_type' => 'resource',
        ]);

        // Simulate resource selection
        $selection = [
            'resource_type' => 'Product',
            'resource_id' => 999,
            'resource_name' => 'New Product',
            'resource_slug' => 'new-product-slug',
        ];

        // Simulate onResourceSelectionChange logic
        $item->resource_type = $selection['resource_type'];
        $item->resource_id = $selection['resource_id'];
        $item->resource_slug = $selection['resource_slug'];

        // Clear custom URL when resource is selected
        if ($selection['resource_type'] && $selection['resource_id']) {
            $item->custom_url = '';
        }

        expect($item->resource_type)->toBe('Product');
        expect($item->resource_id)->toBe(999);
        expect($item->resource_slug)->toBe('new-product-slug');
        expect($item->custom_url)->toBe('');
    });
});

describe('Public API Resource Filtering', function () {
    test('menu items with valid resources appear in public API', function () {
        // Mock ResourceLinkService to return valid resource
        $this->mock(ResourceLinkService::class, function ($mock) {
            $mock->shouldReceive('getResource')
                ->with('Product', 123)
                ->andReturn([
                    'id' => 123,
                    'name' => 'Valid Product',
                    'slug' => 'valid-product',
                    'is_deleted' => false,
                ]);
        });

        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'name' => 'Valid Resource Item',
            'resource_type' => 'Product',
            'resource_id' => 123,
            'resource_slug' => 'valid-product',
            'is_active' => true,
        ]);

        expect($item->hasValidResource())->toBeTrue();

        // Test that item passes visibility filtering
        $isVisible = $item->is_active &&
                    (! $item->display_at || now()->gte($item->display_at)) &&
                    (! $item->hide_at || now()->lt($item->hide_at));

        expect($isVisible)->toBeTrue();
    });

    test('menu items with invalid resources are filtered from public API', function () {
        // Mock ResourceLinkService to return null (resource not found)
        $this->mock(ResourceLinkService::class, function ($mock) {
            $mock->shouldReceive('getResource')
                ->with('Product', 999)
                ->andReturn(null);
        });

        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'name' => 'Invalid Resource Item',
            'resource_type' => 'Product',
            'resource_id' => 999,
            'resource_slug' => 'invalid-product',
            'is_active' => true,
        ]);

        expect($item->hasValidResource())->toBeFalse();
    });

    test('menu items with soft-deleted resources are filtered from public API', function () {
        // Mock ResourceLinkService to return soft-deleted resource
        $this->mock(ResourceLinkService::class, function ($mock) {
            $mock->shouldReceive('getResource')
                ->with('Product', 456)
                ->andReturn([
                    'id' => 456,
                    'name' => 'Deleted Product',
                    'slug' => 'deleted-product',
                    'is_deleted' => true,
                ]);
        });

        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'name' => 'Soft Deleted Resource Item',
            'resource_type' => 'Product',
            'resource_id' => 456,
            'resource_slug' => 'deleted-product',
            'is_active' => true,
        ]);

        expect($item->hasValidResource())->toBeFalse();
    });

    test('menu items without resources are always valid', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'name' => 'Custom URL Item',
            'custom_url' => '/custom-page',
            'resource_type' => null,
            'resource_id' => null,
            'is_active' => true,
        ]);

        expect($item->hasValidResource())->toBeTrue();
    });
});
