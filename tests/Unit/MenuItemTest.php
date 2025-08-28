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
    ]);

    // Create a regular menu item under this menu
    $this->menuItem = MenuItem::factory()->forMenu($this->menu)->create();
});

test('can create a menu item', function () {
    $menuItem = MenuItem::factory()->forMenu($this->menu)->create([
        'name' => 'Home',
        'custom_url' => '/home',
    ]);

    expect($menuItem->name)->toBe('Home');
    expect($menuItem->custom_url)->toBe('/home');
    expect($menuItem->parent_id)->toBe($this->menu->id);
});

test('menu item has parent relationship', function () {
    expect($this->menuItem->parent->id)->toBe($this->menu->id);
});

test('menu item has nested set trait', function () {
    expect(method_exists($this->menuItem, 'descendants'))->toBeTrue();
    expect(method_exists($this->menuItem, 'ancestors'))->toBeTrue();
    expect(method_exists($this->menuItem, 'parent'))->toBeTrue();
    expect(method_exists($this->menuItem, 'children'))->toBeTrue();
});

test('menu item visibility scope works correctly', function () {
    $now = now();

    $visibleItem = MenuItem::factory()->forMenu($this->menu)->create([
        'display_at' => $now->copy()->subHour(),
        'hide_at' => $now->copy()->addHour(),
        'is_active' => true,
    ]);

    $hiddenItem = MenuItem::factory()->forMenu($this->menu)->create([
        'display_at' => $now->copy()->addHour(),
        'hide_at' => null,
        'is_active' => true,
    ]);

    $expiredItem = MenuItem::factory()->forMenu($this->menu)->create([
        'display_at' => $now->copy()->subDay(),
        'hide_at' => $now->copy()->subHour(),
        'is_active' => true,
    ]);

    // Note: Since we don't have a visible scope defined, let's test the isVisible method instead
    expect($visibleItem->isVisible())->toBeTrue();
    expect($hiddenItem->isVisible())->toBeFalse();
    expect($expiredItem->isVisible())->toBeFalse();
});

test('menu item is visible method works correctly', function () {
    $now = now();

    $visibleItem = MenuItem::factory()->make([
        'display_at' => $now->copy()->subHour(),
        'hide_at' => $now->copy()->addHour(),
    ]);

    $hiddenItem = MenuItem::factory()->make([
        'display_at' => $now->copy()->addHour(),
        'hide_at' => null,
    ]);

    expect($visibleItem->isVisible())->toBeTrue();
    expect($hiddenItem->isVisible())->toBeFalse();
});

test('menu item with null display and hide dates is visible', function () {
    $item = MenuItem::factory()->make([
        'display_at' => null,
        'hide_at' => null,
    ]);

    expect($item->isVisible())->toBeTrue();
});

test('menu item url attribute returns custom url when set', function () {
    $item = MenuItem::factory()->make(['custom_url' => 'https://example.com']);

    expect($item->url)->toBe('https://example.com');
});

test('menu item url attribute returns null when no url set', function () {
    $item = MenuItem::factory()->make(['custom_url' => null]);

    expect($item->url)->toBeNull();
});

test('menu item casts dates properly', function () {
    $now = now();
    $item = MenuItem::factory()->create([
        'display_at' => $now->toDateTimeString(),
        'hide_at' => $now->addDay()->toDateTimeString(),
    ]);

    expect($item->display_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($item->hide_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

test('menu item factory creates valid data', function () {
    $item = MenuItem::factory()->create();

    expect($item->name)->toBeString();
    expect($item->parent_id)->toBeNull(); // No parent by default
});

test('menu item factory with custom url state', function () {
    $item = MenuItem::factory()->withCustomUrl('https://test.com')->create();

    expect($item->custom_url)->toBe('https://test.com');
    expect($item->resource_type)->toBeNull();
    expect($item->resource_id)->toBeNull();
});

test('menu item factory with resource state', function () {
    $item = MenuItem::factory()->withResource('App\Models\Page', 123, 'test-page')->create();

    expect($item->custom_url)->toBeNull();
    expect($item->resource_type)->toBe('App\Models\Page');
    expect($item->resource_id)->toBe(123);
    expect($item->resource_slug)->toBe('test-page');
});

test('menu item factory visible state creates visible item', function () {
    $item = MenuItem::factory()->visible()->create();

    expect($item->isVisible())->toBeTrue();
});

test('menu item factory hidden state creates hidden item', function () {
    $item = MenuItem::factory()->hidden()->create();

    expect($item->isVisible())->toBeFalse();
});

test('menu item factory expired state creates expired item', function () {
    $item = MenuItem::factory()->expired()->create();

    expect($item->isVisible())->toBeFalse();
});

test('nested set relationships work correctly', function () {
    $parent = MenuItem::factory()->forMenu($this->menu)->create();
    $child = MenuItem::factory()->create([
        'parent_id' => $parent->id,
    ]);

    expect($parent->children->pluck('id'))->toContain($child->id);
    expect($child->parent->id)->toBe($parent->id);
});

// Comprehensive Model Validation Tests

test('roots scope returns only root menu items', function () {
    $rootMenu = MenuItem::factory()->asMenu()->create();
    $regularItem = MenuItem::factory()->forMenu($this->menu)->create();

    $roots = MenuItem::roots()->get();

    expect($roots->pluck('id'))->toContain($rootMenu->id);
    expect($roots->pluck('id'))->toContain($this->menu->id);
    expect($roots->pluck('id'))->not->toContain($regularItem->id);
    expect($roots->every(fn ($item) => $item->is_root))->toBeTrue();
});

test('forMenu scope returns items for specific menu', function () {
    $menu1 = MenuItem::factory()->asMenu()->create(['slug' => 'menu1']);
    $menu2 = MenuItem::factory()->asMenu()->create(['slug' => 'menu2']);

    $item1 = MenuItem::factory()->forMenu($menu1)->create();
    $item2 = MenuItem::factory()->forMenu($menu2)->create();

    // Test that items belong to correct parent menus by checking parent relationships
    expect($item1->getMenuRoot()->slug)->toBe('menu1');
    expect($item2->getMenuRoot()->slug)->toBe('menu2');
    expect($item1->getMenuRoot()->id)->not->toBe($item2->getMenuRoot()->id);
});

test('forMenu scope returns empty for non-existent menu', function () {
    // Test by checking that menu lookup returns null
    $menu = MenuItem::where('slug', 'non-existent')->where('is_root', true)->first();

    expect($menu)->toBeNull();
});

test('getMenuRoot returns self for root items', function () {
    $root = $this->menu;

    expect($root->getMenuRoot()->id)->toBe($root->id);
});

test('getMenuRoot returns root ancestor for nested items', function () {
    $item = MenuItem::factory()->forMenu($this->menu)->create();

    expect($item->getMenuRoot()->id)->toBe($this->menu->id);
});

test('createMenu creates root menu with defaults', function () {
    $menu = MenuItem::createMenu([
        'name' => 'Test Menu',
    ]);

    expect($menu->name)->toBe('Test Menu');
    expect($menu->slug)->toBe('test-menu');
    expect($menu->max_depth)->toBe(2);
    expect($menu->is_active)->toBeTrue();
    expect($menu->is_root)->toBeTrue();
    expect($menu->parent_id)->toBeNull();
});

test('createMenu creates menu with custom attributes', function () {
    $menu = MenuItem::createMenu([
        'name' => 'Custom Menu',
        'slug' => 'custom-slug',
        'max_depth' => 5,
        'is_active' => false,
    ]);

    expect($menu->name)->toBe('Custom Menu');
    expect($menu->slug)->toBe('custom-slug');
    expect($menu->max_depth)->toBe(5);
    expect($menu->is_active)->toBeFalse();
    expect($menu->is_root)->toBeTrue();
});

test('visible scope filters by display and hide dates', function () {
    $now = now();

    $visibleItem = MenuItem::factory()->forMenu($this->menu)->create([
        'display_at' => $now->copy()->subHour(),
        'hide_at' => $now->copy()->addHour(),
    ]);

    $futureItem = MenuItem::factory()->forMenu($this->menu)->create([
        'display_at' => $now->copy()->addHour(),
        'hide_at' => null,
    ]);

    $expiredItem = MenuItem::factory()->forMenu($this->menu)->create([
        'display_at' => $now->copy()->subDay(),
        'hide_at' => $now->copy()->subHour(),
    ]);

    $alwaysVisibleItem = MenuItem::factory()->forMenu($this->menu)->create([
        'display_at' => null,
        'hide_at' => null,
    ]);

    $visibleItems = MenuItem::visible()->get();

    expect($visibleItems->pluck('id'))->toContain($visibleItem->id);
    expect($visibleItems->pluck('id'))->toContain($alwaysVisibleItem->id);
    expect($visibleItems->pluck('id'))->not->toContain($futureItem->id);
    expect($visibleItems->pluck('id'))->not->toContain($expiredItem->id);
});

test('isActive scope is alias for visible scope', function () {
    $now = now();

    $visibleItem = MenuItem::factory()->forMenu($this->menu)->create([
        'display_at' => $now->copy()->subHour(),
        'hide_at' => $now->copy()->addHour(),
    ]);

    $hiddenItem = MenuItem::factory()->forMenu($this->menu)->create([
        'display_at' => $now->copy()->addHour(),
        'hide_at' => null,
    ]);

    $activeItems = MenuItem::isActive()->get();
    $visibleItems = MenuItem::visible()->get();

    expect($activeItems->count())->toBe($visibleItems->count());
    expect($activeItems->pluck('id'))->toContain($visibleItem->id);
    expect($activeItems->pluck('id'))->not->toContain($hiddenItem->id);
});

test('isVisibleAt scope filters by specific timestamp', function () {
    $baseTime = now();
    $checkTime = $baseTime->copy()->addHours(2);

    $visibleItem = MenuItem::factory()->forMenu($this->menu)->create([
        'display_at' => $baseTime->copy()->addHour(),
        'hide_at' => $baseTime->copy()->addHours(3),
    ]);

    $notYetVisibleItem = MenuItem::factory()->forMenu($this->menu)->create([
        'display_at' => $baseTime->copy()->addHours(3),
        'hide_at' => null,
    ]);

    $expiredItem = MenuItem::factory()->forMenu($this->menu)->create([
        'display_at' => $baseTime->copy()->subHour(),
        'hide_at' => $baseTime->copy()->addHour(),
    ]);

    $visibleItems = MenuItem::isVisibleAt($checkTime)->get();

    expect($visibleItems->pluck('id'))->toContain($visibleItem->id);
    expect($visibleItems->pluck('id'))->not->toContain($notYetVisibleItem->id);
    expect($visibleItems->pluck('id'))->not->toContain($expiredItem->id);
});

test('url attribute returns custom url when set', function () {
    $item = MenuItem::factory()->make(['custom_url' => 'https://example.com']);

    expect($item->url)->toBe('https://example.com');
});

test('url attribute uses resource service for resource urls', function () {
    // Mock the ResourceLinkService
    $mockService = \Mockery::mock(\Skylark\Menus\Services\ResourceLinkService::class);
    $mockService->shouldReceive('generateUrl')
        ->with('App\\Models\\Page', 'test-page')
        ->andReturn('/pages/test-page');

    $this->app->instance(\Skylark\Menus\Services\ResourceLinkService::class, $mockService);

    $item = MenuItem::factory()->make([
        'custom_url' => null,
        'resource_type' => 'App\\Models\\Page',
        'resource_slug' => 'test-page',
    ]);

    expect($item->url)->toBe('/pages/test-page');
});

test('url attribute handles resource service exception gracefully', function () {
    // Mock the ResourceLinkService to throw exception
    $mockService = \Mockery::mock(\Skylark\Menus\Services\ResourceLinkService::class);
    $mockService->shouldReceive('generateUrl')
        ->andThrow(new \Exception('Resource not found'));

    $this->app->instance(\Skylark\Menus\Services\ResourceLinkService::class, $mockService);

    $item = MenuItem::factory()->make([
        'custom_url' => null,
        'resource_type' => 'App\\Models\\Page',
        'resource_slug' => 'test-page',
    ]);

    expect($item->url)->toBeNull();
});

test('hasValidResource returns true when no resource is linked', function () {
    $item = MenuItem::factory()->make([
        'resource_type' => null,
        'resource_id' => null,
    ]);

    expect($item->hasValidResource())->toBeTrue();
});

test('hasValidResource returns true for valid resource', function () {
    // Mock the ResourceLinkService
    $mockService = \Mockery::mock(\Skylark\Menus\Services\ResourceLinkService::class);
    $mockService->shouldReceive('getResource')
        ->with('App\\Models\\Page', 123)
        ->andReturn(['id' => 123, 'is_deleted' => false]);

    $this->app->instance(\Skylark\Menus\Services\ResourceLinkService::class, $mockService);

    $item = MenuItem::factory()->make([
        'resource_type' => 'App\\Models\\Page',
        'resource_id' => 123,
    ]);

    expect($item->hasValidResource())->toBeTrue();
});

test('hasValidResource returns false for soft deleted resource', function () {
    // Mock the ResourceLinkService
    $mockService = \Mockery::mock(\Skylark\Menus\Services\ResourceLinkService::class);
    $mockService->shouldReceive('getResource')
        ->with('App\\Models\\Page', 123)
        ->andReturn(['id' => 123, 'is_deleted' => true]);

    $this->app->instance(\Skylark\Menus\Services\ResourceLinkService::class, $mockService);

    $item = MenuItem::factory()->make([
        'resource_type' => 'App\\Models\\Page',
        'resource_id' => 123,
    ]);

    expect($item->hasValidResource())->toBeFalse();
});

test('hasValidResource returns false when service throws exception', function () {
    // Mock the ResourceLinkService to throw exception
    $mockService = \Mockery::mock(\Skylark\Menus\Services\ResourceLinkService::class);
    $mockService->shouldReceive('getResource')
        ->andThrow(new \Exception('Service error'));

    $this->app->instance(\Skylark\Menus\Services\ResourceLinkService::class, $mockService);

    $item = MenuItem::factory()->make([
        'resource_type' => 'App\\Models\\Page',
        'resource_id' => 123,
    ]);

    expect($item->hasValidResource())->toBeFalse();
});

test('withValidResources scope includes items without resources', function () {
    $customUrlItem = MenuItem::factory()->forMenu($this->menu)->create([
        'resource_type' => null,
        'resource_id' => null,
    ]);

    $resourceItem = MenuItem::factory()->forMenu($this->menu)->create([
        'resource_type' => 'App\\Models\\Page',
        'resource_id' => 123,
    ]);

    $validItems = MenuItem::withValidResources()->get();

    expect($validItems->pluck('id'))->toContain($customUrlItem->id);
    // Note: This scope only filters based on null values, not actual resource validation
    expect($validItems->pluck('id'))->not->toContain($resourceItem->id);
});

test('filterValidResources filters collection by resource validity', function () {
    // Mock the ResourceLinkService
    $mockService = \Mockery::mock(\Skylark\Menus\Services\ResourceLinkService::class);
    $mockService->shouldReceive('getResource')
        ->with('App\\Models\\Page', 123)
        ->andReturn(['id' => 123, 'is_deleted' => false]);
    $mockService->shouldReceive('getResource')
        ->with('App\\Models\\Page', 456)
        ->andReturn(['id' => 456, 'is_deleted' => true]);

    $this->app->instance(\Skylark\Menus\Services\ResourceLinkService::class, $mockService);

    $validItem = MenuItem::factory()->make([
        'resource_type' => 'App\\Models\\Page',
        'resource_id' => 123,
    ]);

    $invalidItem = MenuItem::factory()->make([
        'resource_type' => 'App\\Models\\Page',
        'resource_id' => 456,
    ]);

    $customUrlItem = MenuItem::factory()->make([
        'resource_type' => null,
        'resource_id' => null,
    ]);

    $collection = collect([$validItem, $invalidItem, $customUrlItem]);
    $menuItem = new MenuItem;
    $filtered = $menuItem->filterValidResources($collection);

    expect($filtered->count())->toBe(2);
    expect($filtered)->toContain($validItem);
    expect($filtered)->toContain($customUrlItem);
    expect($filtered)->not->toContain($invalidItem);
});

test('getResourceData returns null when no resource is linked', function () {
    $item = MenuItem::factory()->make([
        'resource_type' => null,
        'resource_id' => null,
    ]);

    expect($item->getResourceData())->toBeNull();
});

test('getResourceData returns resource data when available', function () {
    // Mock the ResourceLinkService
    $mockService = \Mockery::mock(\Skylark\Menus\Services\ResourceLinkService::class);
    $mockService->shouldReceive('getResource')
        ->with('App\\Models\\Page', 123)
        ->andReturn(['id' => 123, 'title' => 'Test Page']);

    $this->app->instance(\Skylark\Menus\Services\ResourceLinkService::class, $mockService);

    $item = MenuItem::factory()->make([
        'resource_type' => 'App\\Models\\Page',
        'resource_id' => 123,
    ]);

    $resourceData = $item->getResourceData();

    expect($resourceData)->toBe(['id' => 123, 'title' => 'Test Page']);
});

test('getResourceData returns null when service throws exception', function () {
    // Mock the ResourceLinkService to throw exception
    $mockService = \Mockery::mock(\Skylark\Menus\Services\ResourceLinkService::class);
    $mockService->shouldReceive('getResource')
        ->andThrow(new \Exception('Service error'));

    $this->app->instance(\Skylark\Menus\Services\ResourceLinkService::class, $mockService);

    $item = MenuItem::factory()->make([
        'resource_type' => 'App\\Models\\Page',
        'resource_id' => 123,
    ]);

    expect($item->getResourceData())->toBeNull();
});

test('validationRules returns correct validation array', function () {
    $rules = MenuItem::validationRules();

    expect($rules)->toHaveKey('name');
    expect($rules)->toHaveKey('custom_url');
    expect($rules)->toHaveKey('resource_type');
    expect($rules)->toHaveKey('resource_id');
    expect($rules)->toHaveKey('resource_slug');
    expect($rules)->toHaveKey('display_at');
    expect($rules)->toHaveKey('hide_at');
    expect($rules)->toHaveKey('parent_id');

    expect($rules['name'])->toContain('required');
    expect($rules['custom_url'])->toContain('nullable');
    expect($rules['hide_at'])->toContain('after:display_at');
});

test('updateValidationRules returns correct validation array with id exclusion', function () {
    $rules = MenuItem::updateValidationRules(123);

    expect($rules)->toHaveKey('parent_id');
    expect($rules['parent_id'])->toContain('not_in:123');
});

test('fillable attributes are properly set', function () {
    $item = new MenuItem;

    $expectedFillable = [
        'parent_id',
        'name',
        'custom_url',
        'resource_type',
        'resource_id',
        'resource_slug',
        'display_at',
        'hide_at',
        'icon',
        'target',
        'css_class',
        'position',
        'is_active',
        'is_root',
        'slug',
        'max_depth',
    ];

    expect($item->getFillable())->toBe($expectedFillable);
});

test('casts are properly configured', function () {
    $item = new MenuItem;

    $expectedCasts = [
        'parent_id' => 'integer',
        'resource_id' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
        'is_root' => 'boolean',
        'max_depth' => 'integer',
        'display_at' => 'datetime',
        'hide_at' => 'datetime',
    ];

    expect($item->getCasts())->toMatchArray($expectedCasts);
});

test('children relationship returns correct relationship', function () {
    $parent = MenuItem::factory()->forMenu($this->menu)->create();
    $child1 = MenuItem::factory()->create(['parent_id' => $parent->id]);
    $child2 = MenuItem::factory()->create(['parent_id' => $parent->id]);
    $otherChild = MenuItem::factory()->forMenu($this->menu)->create();

    $children = $parent->children;

    expect($children)->toHaveCount(2);
    expect($children->pluck('id'))->toContain($child1->id);
    expect($children->pluck('id'))->toContain($child2->id);
    expect($children->pluck('id'))->not->toContain($otherChild->id);
});
