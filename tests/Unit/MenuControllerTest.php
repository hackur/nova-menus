<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Skylark\Menus\Http\Controllers\MenuController;
use Skylark\Menus\Models\MenuItem;
use Skylark\Menus\Services\ResourceLinkService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
    $this->controller = new MenuController;

    // Create test root menus
    $this->rootMenu = MenuItem::factory()->asMenu()->create([
        'name' => 'Main Menu',
        'slug' => 'main-menu',
        'max_depth' => 5,
    ]);
});

describe('MenuController::index', function () {
    test('returns list of root menus with items count', function () {
        // Create another root menu
        $secondMenu = MenuItem::factory()->asMenu()->create([
            'name' => 'Footer Menu',
            'slug' => 'footer-menu',
        ]);

        // Add items to first menu
        MenuItem::factory()->forMenu($this->rootMenu)->create();
        MenuItem::factory()->forMenu($this->rootMenu)->create();

        $response = $this->controller->index();

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();
        expect($data['data'])->toHaveCount(2);

        $mainMenu = collect($data['data'])->firstWhere('slug', 'main-menu');
        expect($mainMenu['items_count'])->toBe(2);

        $footerMenu = collect($data['data'])->firstWhere('slug', 'footer-menu');
        expect($footerMenu['items_count'])->toBe(0);
    });

    test('handles empty menu list', function () {
        // Delete test menu
        $this->rootMenu->delete();

        $response = $this->controller->index();

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();
        expect($data['data'])->toHaveCount(0);
    });

});

describe('MenuController::store', function () {
    test('creates new root menu with valid data', function () {
        $request = new Request([
            'name' => 'New Menu',
            'slug' => 'new-menu',
            'is_active' => true,
            'max_depth' => 6,
        ]);

        $response = $this->controller->store($request);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();
        expect($data['data']['name'])->toBe('New Menu');
        expect($data['data']['slug'])->toBe('new-menu');
        expect($data['data']['is_root'])->toBeTrue();

        // Verify in database
        $menu = MenuItem::where('slug', 'new-menu')->first();
        expect($menu)->not->toBeNull();
        expect($menu->is_root)->toBeTrue();
    });

    test('generates slug from name when not provided', function () {
        $request = new Request([
            'name' => 'Auto Generated Slug',
        ]);

        $response = $this->controller->store($request);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['data']['slug'])->toBe('auto-generated-slug');
    });

    test('returns validation error for missing required fields', function () {
        $request = new Request([
            'name' => 'A', // Too short
        ]);

        $response = $this->controller->store($request);

        expect($response->getStatusCode())->toBe(422);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeFalse();
        expect($data['message'])->toBe('Validation failed');
        expect($data['errors'])->toHaveKey('name');
    });

    test('returns validation error for duplicate slug', function () {
        $request = new Request([
            'name' => 'Duplicate Menu',
            'slug' => 'main-menu', // Already exists
        ]);

        $response = $this->controller->store($request);

        expect($response->getStatusCode())->toBe(422);

        $data = json_decode($response->getContent(), true);
        expect($data['errors'])->toHaveKey('slug');
    });
});

describe('MenuController::show', function () {
    test('returns menu when found', function () {
        $response = $this->controller->show($this->rootMenu->id);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();
        expect($data['data']['id'])->toBe($this->rootMenu->id);
        expect($data['data']['name'])->toBe($this->rootMenu->name);
    });

    test('returns 404 for non-existent menu', function () {
        $response = $this->controller->show(99999);

        expect($response->getStatusCode())->toBe(404);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeFalse();
        expect($data['message'])->toBe('Menu not found');
    });

    test('returns 404 for non-root menu item', function () {
        $childItem = MenuItem::factory()->forMenu($this->rootMenu)->create();

        $response = $this->controller->show($childItem->id);

        expect($response->getStatusCode())->toBe(404);
    });
});

describe('MenuController::update', function () {
    test('updates menu with valid data', function () {
        $request = new Request([
            'name' => 'Updated Menu Name',
            'is_active' => false,
            'max_depth' => 8,
        ]);

        $response = $this->controller->update($request, $this->rootMenu->id);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();
        expect($data['data']['name'])->toBe('Updated Menu Name');
        expect($data['data']['is_active'])->toBeFalse();
        expect($data['data']['max_depth'])->toBe(8);
    });

    test('handles partial updates', function () {
        $originalName = $this->rootMenu->name;

        $request = new Request([
            'is_active' => false,
        ]);

        $response = $this->controller->update($request, $this->rootMenu->id);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['data']['name'])->toBe($originalName); // Unchanged
        expect($data['data']['is_active'])->toBeFalse(); // Changed
    });

    test('returns validation error for invalid data', function () {
        $request = new Request([
            'max_depth' => 15, // Exceeds max limit
        ]);

        $response = $this->controller->update($request, $this->rootMenu->id);

        expect($response->getStatusCode())->toBe(422);
    });

    test('returns 404 for non-existent menu', function () {
        $request = new Request(['name' => 'Test']);

        $response = $this->controller->update($request, 99999);

        expect($response->getStatusCode())->toBe(404);
    });
});

describe('MenuController::destroy', function () {
    test('deletes menu and all descendants', function () {
        $childItem = MenuItem::factory()->forMenu($this->rootMenu)->create();
        $grandChildItem = MenuItem::factory()->create(['parent_id' => $childItem->id]);

        $response = $this->controller->destroy($this->rootMenu->id);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();

        // Verify deletion
        expect(MenuItem::find($this->rootMenu->id))->toBeNull();
        expect(MenuItem::find($childItem->id))->toBeNull();
        expect(MenuItem::find($grandChildItem->id))->toBeNull();
    });

    test('returns 404 for non-existent menu', function () {
        $response = $this->controller->destroy(99999);

        expect($response->getStatusCode())->toBe(404);
    });
});

describe('MenuController::items', function () {
    test('returns hierarchical menu items structure', function () {
        $parent = MenuItem::factory()->forMenu($this->rootMenu)->create(['name' => 'Parent']);
        $child = MenuItem::factory()->create(['parent_id' => $parent->id, 'name' => 'Child']);

        $response = $this->controller->items($this->rootMenu->id);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();
        expect($data['data'])->toBeArray();
    });

    test('returns empty array for menu with no items', function () {
        $response = $this->controller->items($this->rootMenu->id);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['data'])->toHaveCount(0);
    });

    test('returns 404 for non-existent menu', function () {
        $response = $this->controller->items(99999);

        expect($response->getStatusCode())->toBe(404);
    });
});

describe('MenuController::storeItem', function () {
    test('creates new menu item with valid data', function () {
        $request = new Request([
            'menu_id' => $this->rootMenu->id,
            'name' => 'New Item',
            'custom_url' => '/new-item',
            'is_active' => true,
        ]);

        $response = $this->controller->storeItem($request);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();
        expect($data['data']['name'])->toBe('New Item');
        expect($data['data']['custom_url'])->toBe('/new-item');
    });

    test('sets default position when not provided', function () {
        // Create existing item
        MenuItem::factory()->forMenu($this->rootMenu)->create(['position' => 5]);

        $request = new Request([
            'menu_id' => $this->rootMenu->id,
            'name' => 'Auto Position Item',
        ]);

        $response = $this->controller->storeItem($request);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['data']['position'])->toBe(6); // Should be next position
    });

    test('returns validation error for invalid menu_id', function () {
        $request = new Request([
            'menu_id' => 99999,
            'name' => 'Test Item',
        ]);

        $response = $this->controller->storeItem($request);

        expect($response->getStatusCode())->toBe(422);

        $data = json_decode($response->getContent(), true);
        expect($data['errors'])->toHaveKey('menu_id');
    });
});

describe('MenuController::updateItem', function () {
    test('updates menu item with valid data', function () {
        $item = MenuItem::factory()->forMenu($this->rootMenu)->create([
            'name' => 'Original Name',
        ]);

        $request = new Request([
            'name' => 'Updated Name',
            'is_active' => false,
        ]);

        $response = $this->controller->updateItem($request, $item->id);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();
        expect($data['data']['name'])->toBe('Updated Name');
        expect($data['data']['is_active'])->toBeFalse();
    });

    test('returns 404 for non-existent item', function () {
        $request = new Request(['name' => 'Test']);

        $response = $this->controller->updateItem($request, 99999);

        expect($response->getStatusCode())->toBe(404);
    });
});

describe('MenuController::destroyItem', function () {
    test('deletes menu item and its children recursively', function () {
        $parent = MenuItem::factory()->forMenu($this->rootMenu)->create();
        $child = MenuItem::factory()->create(['parent_id' => $parent->id]);

        $response = $this->controller->destroyItem($parent->id);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();

        // Verify deletion
        expect(MenuItem::find($parent->id))->toBeNull();
        expect(MenuItem::find($child->id))->toBeNull();
    });

    test('returns 404 for non-existent item', function () {
        $response = $this->controller->destroyItem(99999);

        expect($response->getStatusCode())->toBe(404);
    });
});

describe('MenuController::resourceTypes', function () {
    test('returns available resource types', function () {
        // Mock ResourceLinkService
        $mockService = $this->mock(ResourceLinkService::class);
        $mockService->shouldReceive('getResourceTypes')->andReturn(['Product', 'Category']);
        $this->app->instance(ResourceLinkService::class, $mockService);

        $response = $this->controller->resourceTypes();

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();
        expect($data['data'])->toHaveKey('Product');
        expect($data['data'])->toHaveKey('Category');
    });

    test('handles service exceptions', function () {
        $mockService = $this->mock(ResourceLinkService::class);
        $mockService->shouldReceive('getResourceTypes')->andThrow(new \Exception('Service error'));
        $this->app->instance(ResourceLinkService::class, $mockService);

        $response = $this->controller->resourceTypes();

        expect($response->getStatusCode())->toBe(500);
    });
});

describe('MenuController::searchResources', function () {
    test('returns search results from resource service', function () {
        $mockService = $this->mock(ResourceLinkService::class);
        $mockService->shouldReceive('searchResources')
            ->with('Product', 'test', 50)
            ->andReturn(collect([
                ['id' => 1, 'name' => 'Test Product', 'slug' => 'test-product'],
            ]));
        $this->app->instance(ResourceLinkService::class, $mockService);

        $request = new Request(['q' => 'test']);
        $response = $this->controller->searchResources($request, 'Product');

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['success'])->toBeTrue();
        expect($data['data'])->toHaveCount(1);
        expect($data['data'][0]['name'])->toBe('Test Product');
    });

    test('handles validation errors', function () {
        $request = new Request(['limit' => 999]); // Exceeds max limit

        $response = $this->controller->searchResources($request, 'Product');

        expect($response->getStatusCode())->toBe(422);
    });

    test('handles invalid argument exceptions', function () {
        $mockService = $this->mock(ResourceLinkService::class);
        $mockService->shouldReceive('searchResources')
            ->andThrow(new \InvalidArgumentException('Invalid resource type'));
        $this->app->instance(ResourceLinkService::class, $mockService);

        $request = new Request;
        $response = $this->controller->searchResources($request, 'InvalidType');

        expect($response->getStatusCode())->toBe(400);
    });
});
