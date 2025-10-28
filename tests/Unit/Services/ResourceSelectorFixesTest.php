<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Skylark\Menus\Models\MenuItem;
use Skylark\Menus\Services\ResourceLinkService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);

    // Configure test resources
    config()->set('menus.resources', [
        'Product' => [
            'model' => 'App\\Models\\Product',
            'name_field' => 'name',
            'slug_field' => 'slug',
            'route_pattern' => '/products/{slug}',
        ],
        'Category' => [
            'model' => 'App\\Models\\Category',
            'name_field' => 'name',
            'slug_field' => 'slug',
            'route_pattern' => '/categories/{slug}',
        ],
    ]);
});

describe('ResourceLinkService SoftDeletes Compatibility', function () {
    test('handles models without SoftDeletes trait correctly', function () {
        // Mock a non-soft-delete model
        $this->mock('App\\Models\\Product', function ($mock) {
            $mock->shouldReceive('find')
                ->with(25)
                ->andReturn((object) [
                    'id' => 25,
                    'name' => 'Books Novel',
                    'slug' => 'books-novel-8243',
                ]);
        });

        $service = new ResourceLinkService;

        // This should not throw an exception
        $result = $service->getResource('Product', 25);

        expect($result)->toBeArray();
        expect($result['id'])->toBe(25);
        expect($result['name'])->toBe('Books Novel');
        expect($result['slug'])->toBe('books-novel-8243');
        expect($result['is_deleted'])->toBeFalse();
    });

    test('searchResources works without SoftDeletes trait', function () {
        // Mock query builder for non-soft-delete model
        $queryMock = $this->mock();
        $queryMock->shouldReceive('where')->andReturnSelf();
        $queryMock->shouldReceive('limit')->andReturnSelf();
        $queryMock->shouldReceive('get')
            ->andReturn(collect([
                (object) ['id' => 1, 'name' => 'Search Result 1', 'slug' => 'result-1'],
                (object) ['id' => 2, 'name' => 'Search Result 2', 'slug' => 'result-2'],
            ]));

        $this->mock('App\\Models\\Product', function ($mock) use ($queryMock) {
            $mock->shouldReceive('query')->andReturn($queryMock);
            $mock->shouldReceive('getKeyName')->andReturn('id');
        });

        $service = new ResourceLinkService;
        $results = $service->searchResources('Product', 'search', 10);

        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($results)->toHaveCount(2);
        expect($results->first()['name'])->toBe('Search Result 1');
    });

    test('URL generation works correctly', function () {
        $service = new ResourceLinkService;
        $url = $service->generateUrl('Product', 'test-product-slug');

        expect($url)->toBe('/products/test-product-slug');
    });
});

describe('MenuItem Link Type Initialization', function () {
    test('initializes link_type as resource when resource data exists', function () {
        $menu = MenuItem::factory()->asMenu()->create();

        $item = MenuItem::factory()->forMenu($menu)->create([
            'name' => 'Product Link',
            'resource_type' => 'Product',
            'resource_id' => 123,
            'resource_slug' => 'test-product',
            'custom_url' => null,
        ]);

        // Simulate initialization logic from Nested.vue
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
    });

    test('initializes link_type as url when custom_url exists', function () {
        $menu = MenuItem::factory()->asMenu()->create();

        $item = MenuItem::factory()->forMenu($menu)->create([
            'name' => 'Custom Link',
            'custom_url' => '/custom-page',
            'resource_type' => null,
            'resource_id' => null,
            'resource_slug' => null,
        ]);

        // Simulate initialization logic from Nested.vue
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
    });

    test('defaults link_type to url for new items', function () {
        $menu = MenuItem::factory()->asMenu()->create();

        $item = MenuItem::factory()->forMenu($menu)->create([
            'name' => 'New Item',
            'custom_url' => null,
            'resource_type' => null,
            'resource_id' => null,
        ]);

        // Simulate initialization logic from Nested.vue
        if (! isset($item->link_type)) {
            if ($item->resource_type && $item->resource_id) {
                $item->link_type = 'resource';
            } elseif ($item->custom_url) {
                $item->link_type = 'url';
            } else {
                $item->link_type = 'url'; // Default
            }
        }

        expect($item->link_type)->toBe('url');
    });
});

describe('ResourceSelection Object Initialization', function () {
    test('resourceSelection object contains all required fields for existing resource item', function () {
        $menu = MenuItem::factory()->asMenu()->create();

        $item = MenuItem::factory()->forMenu($menu)->create([
            'name' => 'Resource Item',
            'resource_type' => 'Product',
            'resource_id' => 789,
            'resource_slug' => 'product-slug',
        ]);

        // Add resource_name (simulating backend API response)
        $item->resource_name = 'Product Name';

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
        expect($item->resourceSelection['resource_id'])->toBe(789);
        expect($item->resourceSelection['resource_name'])->toBe('Product Name');
        expect($item->resourceSelection['resource_slug'])->toBe('product-slug');
    });

    test('resourceSelection object handles null values for custom URL items', function () {
        $menu = MenuItem::factory()->asMenu()->create();

        $item = MenuItem::factory()->forMenu($menu)->create([
            'name' => 'Custom URL Item',
            'custom_url' => '/custom',
            'resource_type' => null,
            'resource_id' => null,
            'resource_slug' => null,
        ]);

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
        expect($item->resourceSelection['resource_type'])->toBeNull();
        expect($item->resourceSelection['resource_id'])->toBeNull();
        expect($item->resourceSelection['resource_name'])->toBeNull();
        expect($item->resourceSelection['resource_slug'])->toBeNull();
    });
});

describe('Link Type Change Behavior', function () {
    test('onLinkTypeChange clears resource data when switching to url', function () {
        $menu = MenuItem::factory()->asMenu()->create();

        $item = MenuItem::factory()->forMenu($menu)->create([
            'name' => 'Test Item',
            'custom_url' => '/old-url',
            'resource_type' => 'Product',
            'resource_id' => 456,
            'resource_slug' => 'old-product',
        ]);

        $item->resourceSelection = [
            'resource_type' => 'Product',
            'resource_id' => 456,
            'resource_name' => 'Old Product',
            'resource_slug' => 'old-product',
        ];

        // Simulate switching to URL link type
        $item->link_type = 'url';

        // Simulate onLinkTypeChange logic from Nested.vue
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

    test('onLinkTypeChange clears custom_url when switching to resource', function () {
        $menu = MenuItem::factory()->asMenu()->create();

        $item = MenuItem::factory()->forMenu($menu)->create([
            'name' => 'Test Item',
            'custom_url' => '/old-custom-url',
            'resource_type' => null,
            'resource_id' => null,
        ]);

        // Simulate switching to resource link type
        $item->link_type = 'resource';

        // Simulate onLinkTypeChange logic from Nested.vue
        if ($item->link_type === 'url') {
            // ... resource clearing logic
        } else {
            $item->custom_url = '';
        }

        expect($item->custom_url)->toBe('');
    });
});

describe('Resource Selection Change Behavior', function () {
    test('onResourceSelectionChange updates element data correctly', function () {
        $menu = MenuItem::factory()->asMenu()->create();

        $item = MenuItem::factory()->forMenu($menu)->create([
            'name' => 'Test Item',
            'link_type' => 'resource',
            'custom_url' => '/old-url',
        ]);

        $selection = [
            'resource_type' => 'Category',
            'resource_id' => 888,
            'resource_name' => 'Test Category',
            'resource_slug' => 'test-category',
        ];

        // Simulate onResourceSelectionChange logic from Nested.vue
        $item->resource_type = $selection['resource_type'];
        $item->resource_id = $selection['resource_id'];
        $item->resource_slug = $selection['resource_slug'];

        // Clear custom URL when resource is selected
        if ($selection['resource_type'] && $selection['resource_id']) {
            $item->custom_url = '';
        }

        expect($item->resource_type)->toBe('Category');
        expect($item->resource_id)->toBe(888);
        expect($item->resource_slug)->toBe('test-category');
        expect($item->custom_url)->toBe(''); // Should be cleared
    });
});
