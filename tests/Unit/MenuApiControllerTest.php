<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Skylark\Menus\Http\Controllers\MenuApiController;
use Skylark\Menus\Models\MenuItem;
use Skylark\Menus\Services\ResourceLinkService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);

    // Mock the ResourceLinkService
    $this->resourceLinkService = $this->mock(ResourceLinkService::class);

    // Create the controller with mocked service
    $this->controller = new MenuApiController($this->resourceLinkService);

    // Create test menu structure
    $this->rootMenu = MenuItem::factory()->asMenu()->create([
        'name' => 'Main Menu',
        'slug' => 'main-menu',
    ]);
});

describe('MenuApiController::getMenu', function () {
    test('returns menu with hierarchical structure when menu exists', function () {
        // Create menu items
        $visibleItem = MenuItem::factory()->forMenu($this->rootMenu)->create([
            'name' => 'About',
            'is_active' => true,
            'display_at' => now()->subHour(),
            'hide_at' => null,
        ]);

        $response = $this->controller->getMenu('main-menu');

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data)->toHaveKeys(['slug', 'name', 'items', 'timestamp']);
        expect($data['slug'])->toBe('main-menu');
        expect($data['name'])->toBe('Main Menu');
    });

    test('returns 404 when menu does not exist', function () {
        $response = $this->controller->getMenu('nonexistent-menu');

        expect($response->getStatusCode())->toBe(404);

        $data = json_decode($response->getContent(), true);
        expect($data)->toHaveKeys(['error', 'message']);
        expect($data['error'])->toBe('Menu not found');
        expect($data['message'])->toBe("Menu with slug 'nonexistent-menu' does not exist");
    });

    test('filters hierarchically hidden items', function () {
        // Create visible parent
        $visibleParent = MenuItem::factory()->forMenu($this->rootMenu)->create([
            'name' => 'Services',
            'is_active' => true,
        ]);

        // Create hidden parent
        $hiddenParent = MenuItem::factory()->forMenu($this->rootMenu)->create([
            'name' => 'Hidden Section',
            'is_active' => false,
        ]);

        // Create children under hidden parent (should also be hidden)
        $childOfHidden = MenuItem::factory()->create([
            'parent_id' => $hiddenParent->id,
            'name' => 'Hidden Child',
            'is_active' => true,
        ]);

        $response = $this->controller->getMenu('main-menu');
        $data = json_decode($response->getContent(), true);

        // Should not contain hidden items or their children
        $itemNames = collect($data['items'])->pluck('name');
        expect($itemNames)->toContain('Services');
        expect($itemNames)->not->toContain('Hidden Section');
        expect($itemNames)->not->toContain('Hidden Child');
    });
});

describe('MenuApiController::getMenus', function () {
    test('returns multiple menus when valid slugs provided', function () {
        $menu1 = MenuItem::factory()->asMenu()->create(['slug' => 'menu-1']);
        $menu2 = MenuItem::factory()->asMenu()->create(['slug' => 'menu-2']);

        $request = new Request(['menus' => 'menu-1,menu-2']);
        $response = $this->controller->getMenus($request);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data)->toHaveKeys(['menus', 'timestamp']);
        expect($data['menus'])->toHaveKeys(['menu-1', 'menu-2']);
    });

    test('returns error when no menus parameter provided', function () {
        $request = new Request;
        $response = $this->controller->getMenus($request);

        expect($response->getStatusCode())->toBe(400);

        $data = json_decode($response->getContent(), true);
        expect($data['error'])->toBe('No menus specified');
        expect($data['message'])->toBe('Please provide comma-separated menu slugs via ?menus=slug1,slug2');
    });

    test('returns error when empty menus parameter provided', function () {
        $request = new Request(['menus' => '']);
        $response = $this->controller->getMenus($request);

        expect($response->getStatusCode())->toBe(400);
    });

    test('handles mixed valid and invalid menu slugs', function () {
        $validMenu = MenuItem::factory()->asMenu()->create(['slug' => 'valid-menu']);

        $request = new Request(['menus' => 'valid-menu,invalid-menu']);
        $response = $this->controller->getMenus($request);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['menus']['valid-menu'])->toHaveKeys(['name', 'items']);
        expect($data['menus']['invalid-menu'])->toHaveKeys(['error', 'message']);
        expect($data['menus']['invalid-menu']['error'])->toBe('Menu not found');
    });

    test('trims whitespace from menu slugs', function () {
        $menu = MenuItem::factory()->asMenu()->create(['slug' => 'test-menu']);

        $request = new Request(['menus' => ' test-menu , ']);
        $response = $this->controller->getMenus($request);

        expect($response->getStatusCode())->toBe(200);

        $data = json_decode($response->getContent(), true);
        expect($data['menus'])->toHaveKey('test-menu');
    });
});

describe('MenuApiController::filterHierarchically', function () {
    test('filters out inactive items', function () {
        $activeItem = MenuItem::factory()->forMenu($this->rootMenu)->create(['is_active' => true]);
        $inactiveItem = MenuItem::factory()->forMenu($this->rootMenu)->create(['is_active' => false]);

        $allItems = collect([$activeItem, $inactiveItem]);

        // Use reflection to test protected method
        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('filterHierarchically');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->controller, [$allItems]);

        expect($result->count())->toBe(1);
        expect($result->first()->id)->toBe($activeItem->id);
    });

    test('filters out items with future display_at date', function () {
        $currentItem = MenuItem::factory()->forMenu($this->rootMenu)->create([
            'is_active' => true,
            'display_at' => now()->subHour(),
        ]);

        $futureItem = MenuItem::factory()->forMenu($this->rootMenu)->create([
            'is_active' => true,
            'display_at' => now()->addHour(),
        ]);

        $allItems = collect([$currentItem, $futureItem]);

        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('filterHierarchically');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->controller, [$allItems]);

        expect($result->count())->toBe(1);
        expect($result->first()->id)->toBe($currentItem->id);
    });

    test('filters out items with past hide_at date', function () {
        $visibleItem = MenuItem::factory()->forMenu($this->rootMenu)->create([
            'is_active' => true,
            'hide_at' => now()->addHour(),
        ]);

        $expiredItem = MenuItem::factory()->forMenu($this->rootMenu)->create([
            'is_active' => true,
            'hide_at' => now()->subHour(),
        ]);

        $allItems = collect([$visibleItem, $expiredItem]);

        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('filterHierarchically');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->controller, [$allItems]);

        expect($result->count())->toBe(1);
        expect($result->first()->id)->toBe($visibleItem->id);
    });
});

describe('MenuApiController::isItemVisible', function () {
    test('returns true for active item with no temporal constraints', function () {
        $item = MenuItem::factory()->make([
            'is_active' => true,
            'display_at' => null,
            'hide_at' => null,
        ]);

        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('isItemVisible');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->controller, [$item]);

        expect($result)->toBeTrue();
    });

    test('returns false for inactive item', function () {
        $item = MenuItem::factory()->make(['is_active' => false]);

        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('isItemVisible');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->controller, [$item]);

        expect($result)->toBeFalse();
    });

    test('returns false when current time is before display_at', function () {
        $item = MenuItem::factory()->make([
            'is_active' => true,
            'display_at' => now()->addHour(),
        ]);

        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('isItemVisible');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->controller, [$item]);

        expect($result)->toBeFalse();
    });

    test('returns false when current time is at or after hide_at', function () {
        $item = MenuItem::factory()->make([
            'is_active' => true,
            'hide_at' => now()->subMinute(),
        ]);

        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('isItemVisible');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->controller, [$item]);

        expect($result)->toBeFalse();
    });

    test('returns true when item is within display window', function () {
        $item = MenuItem::factory()->make([
            'is_active' => true,
            'display_at' => now()->subHour(),
            'hide_at' => now()->addHour(),
        ]);

        $reflection = new ReflectionClass($this->controller);
        $method = $reflection->getMethod('isItemVisible');
        $method->setAccessible(true);

        $result = $method->invokeArgs($this->controller, [$item]);

        expect($result)->toBeTrue();
    });
});
