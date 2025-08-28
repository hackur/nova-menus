<?php

use Carbon\Carbon;
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

    // Set a fixed time for consistent testing
    $this->baseTime = Carbon::create(2025, 1, 15, 12, 0, 0);
    Carbon::setTestNow($this->baseTime);
});

afterEach(function () {
    Carbon::setTestNow(); // Reset time
});

describe('Temporal Visibility Scopes', function () {
    test('visible scope includes items without temporal constraints', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => null,
            'hide_at' => null,
            'is_active' => true,
        ]);

        $visibleItems = MenuItem::visible()->get();

        expect($visibleItems->pluck('id'))->toContain($item->id);
    });

    test('visible scope includes items within display window', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->subHour(),
            'hide_at' => $this->baseTime->copy()->addHour(),
            'is_active' => true,
        ]);

        $visibleItems = MenuItem::visible()->get();

        expect($visibleItems->pluck('id'))->toContain($item->id);
    });

    test('visible scope excludes items before display time', function () {
        $futureItem = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->addHour(),
            'hide_at' => null,
            'is_active' => true,
        ]);

        $visibleItems = MenuItem::visible()->get();

        expect($visibleItems->pluck('id'))->not->toContain($futureItem->id);
    });

    test('visible scope excludes items after hide time', function () {
        $expiredItem = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->subDay(),
            'hide_at' => $this->baseTime->copy()->subHour(),
            'is_active' => true,
        ]);

        $visibleItems = MenuItem::visible()->get();

        expect($visibleItems->pluck('id'))->not->toContain($expiredItem->id);
    });

    test('visible scope handles edge case when display time equals current time', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime,
            'hide_at' => $this->baseTime->copy()->addHour(),
            'is_active' => true,
        ]);

        $visibleItems = MenuItem::visible()->get();

        expect($visibleItems->pluck('id'))->toContain($item->id);
    });

    test('visible scope handles edge case when hide time equals current time', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->subHour(),
            'hide_at' => $this->baseTime,
            'is_active' => true,
        ]);

        $visibleItems = MenuItem::visible()->get();

        expect($visibleItems->pluck('id'))->not->toContain($item->id);
    });

    test('visible scope with null display_at shows immediately', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => null,
            'hide_at' => $this->baseTime->copy()->addDay(),
            'is_active' => true,
        ]);

        $visibleItems = MenuItem::visible()->get();

        expect($visibleItems->pluck('id'))->toContain($item->id);
    });

    test('visible scope with null hide_at shows indefinitely', function () {
        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->subDay(),
            'hide_at' => null,
            'is_active' => true,
        ]);

        $visibleItems = MenuItem::visible()->get();

        expect($visibleItems->pluck('id'))->toContain($item->id);
    });
});

describe('isVisibleAt Scope', function () {
    test('isVisibleAt scope filters by specific past timestamp', function () {
        $checkTime = $this->baseTime->copy()->subHours(2);

        $pastVisible = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->subHours(3),
            'hide_at' => $this->baseTime->copy()->subHour(),
            'is_active' => true,
        ]);

        $futureItem = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->subHour(),
            'hide_at' => $this->baseTime->copy()->addHour(),
            'is_active' => true,
        ]);

        $visibleItems = MenuItem::isVisibleAt($checkTime)->get();

        expect($visibleItems->pluck('id'))->toContain($pastVisible->id);
        expect($visibleItems->pluck('id'))->not->toContain($futureItem->id);
    });

    test('isVisibleAt scope filters by specific future timestamp', function () {
        $checkTime = $this->baseTime->copy()->addHours(2);

        $futureVisible = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->addHour(),
            'hide_at' => $this->baseTime->copy()->addHours(3),
            'is_active' => true,
        ]);

        $currentItem = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->subHour(),
            'hide_at' => $this->baseTime->copy()->addHour(),
            'is_active' => true,
        ]);

        $visibleItems = MenuItem::isVisibleAt($checkTime)->get();

        expect($visibleItems->pluck('id'))->toContain($futureVisible->id);
        expect($visibleItems->pluck('id'))->not->toContain($currentItem->id);
    });

    test('isVisibleAt scope handles null display_at correctly', function () {
        $checkTime = $this->baseTime->copy()->subDay();

        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => null,
            'hide_at' => $this->baseTime->copy()->addDay(),
            'is_active' => true,
        ]);

        $visibleItems = MenuItem::isVisibleAt($checkTime)->get();

        expect($visibleItems->pluck('id'))->toContain($item->id);
    });

    test('isVisibleAt scope handles null hide_at correctly', function () {
        $checkTime = $this->baseTime->copy()->addDay();

        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->subDay(),
            'hide_at' => null,
            'is_active' => true,
        ]);

        $visibleItems = MenuItem::isVisibleAt($checkTime)->get();

        expect($visibleItems->pluck('id'))->toContain($item->id);
    });

    test('isVisibleAt scope with exact timestamp boundaries', function () {
        $exactDisplayTime = $this->baseTime->copy()->addHour();
        $exactHideTime = $this->baseTime->copy()->addHours(2);

        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $exactDisplayTime,
            'hide_at' => $exactHideTime,
            'is_active' => true,
        ]);

        // Check at display time (should be visible)
        $visibleAtDisplay = MenuItem::isVisibleAt($exactDisplayTime)->get();
        expect($visibleAtDisplay->pluck('id'))->toContain($item->id);

        // Check at hide time (should not be visible)
        $visibleAtHide = MenuItem::isVisibleAt($exactHideTime)->get();
        expect($visibleAtHide->pluck('id'))->not->toContain($item->id);

        // Check just before display time
        $beforeDisplay = MenuItem::isVisibleAt($exactDisplayTime->copy()->subSecond())->get();
        expect($beforeDisplay->pluck('id'))->not->toContain($item->id);

        // Check just before hide time
        $beforeHide = MenuItem::isVisibleAt($exactHideTime->copy()->subSecond())->get();
        expect($beforeHide->pluck('id'))->toContain($item->id);
    });
});

describe('isActive Scope Alias', function () {
    test('isActive scope behaves identically to visible scope', function () {
        $visibleItem = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->subHour(),
            'hide_at' => $this->baseTime->copy()->addHour(),
            'is_active' => true,
        ]);

        $hiddenItem = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->addHour(),
            'hide_at' => null,
            'is_active' => true,
        ]);

        $activeItems = MenuItem::isActive()->get()->pluck('id')->toArray();
        $visibleItems = MenuItem::visible()->get()->pluck('id')->toArray();

        expect($activeItems)->toBe($visibleItems);
        expect($activeItems)->toContain($visibleItem->id);
        expect($activeItems)->not->toContain($hiddenItem->id);
    });

    test('isActive scope returns same count as visible scope', function () {
        MenuItem::factory()->forMenu($this->menu)->count(5)->create([
            'display_at' => $this->baseTime->copy()->subHour(),
            'hide_at' => $this->baseTime->copy()->addHour(),
            'is_active' => true,
        ]);

        MenuItem::factory()->forMenu($this->menu)->count(3)->create([
            'display_at' => $this->baseTime->copy()->addHour(),
            'hide_at' => null,
            'is_active' => true,
        ]);

        expect(MenuItem::isActive()->count())->toBe(MenuItem::visible()->count());
    });
});

describe('Roots Scope', function () {
    test('roots scope returns only menu items marked as root', function () {
        $rootMenu1 = MenuItem::factory()->asMenu()->create(['name' => 'Root 1']);
        $rootMenu2 = MenuItem::factory()->asMenu()->create(['name' => 'Root 2']);

        $childItem = MenuItem::factory()->forMenu($rootMenu1)->create();

        $roots = MenuItem::roots()->get();

        expect($roots->count())->toBeGreaterThanOrEqual(3); // including $this->menu
        expect($roots->pluck('id'))->toContain($rootMenu1->id);
        expect($roots->pluck('id'))->toContain($rootMenu2->id);
        expect($roots->pluck('id'))->toContain($this->menu->id);
        expect($roots->pluck('id'))->not->toContain($childItem->id);

        expect($roots->every(fn ($item) => $item->is_root))->toBeTrue();
    });

    test('roots scope with temporal filtering combination', function () {
        $visibleRoot = MenuItem::factory()->asMenu()->create([
            'name' => 'Visible Root',
            'display_at' => $this->baseTime->copy()->subHour(),
            'hide_at' => $this->baseTime->copy()->addHour(),
        ]);

        $hiddenRoot = MenuItem::factory()->asMenu()->create([
            'name' => 'Hidden Root',
            'display_at' => $this->baseTime->copy()->addHour(),
            'hide_at' => null,
        ]);

        $visibleRoots = MenuItem::roots()->visible()->get();
        $allRoots = MenuItem::roots()->get();

        expect($allRoots->count())->toBeGreaterThan($visibleRoots->count());
        expect($visibleRoots->pluck('id'))->toContain($visibleRoot->id);
        expect($visibleRoots->pluck('id'))->not->toContain($hiddenRoot->id);
    });
});

describe('Complex Scope Combinations', function () {
    test('combining roots and visible scopes', function () {
        $visibleRoot = MenuItem::factory()->asMenu()->create([
            'display_at' => $this->baseTime->copy()->subHour(),
            'hide_at' => $this->baseTime->copy()->addHour(),
        ]);

        $hiddenRoot = MenuItem::factory()->asMenu()->create([
            'display_at' => $this->baseTime->copy()->addHour(),
            'hide_at' => null,
        ]);

        $visibleChild = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->subHour(),
            'hide_at' => $this->baseTime->copy()->addHour(),
        ]);

        $visibleRoots = MenuItem::roots()->visible()->get();

        expect($visibleRoots->every(fn ($item) => $item->is_root))->toBeTrue();
        expect($visibleRoots->pluck('id'))->toContain($visibleRoot->id);
        expect($visibleRoots->pluck('id'))->not->toContain($hiddenRoot->id);
        expect($visibleRoots->pluck('id'))->not->toContain($visibleChild->id);
    });

    test('combining isVisibleAt with roots scope', function () {
        $pastTime = $this->baseTime->copy()->subHours(2);

        $pastVisibleRoot = MenuItem::factory()->asMenu()->create([
            'display_at' => $this->baseTime->copy()->subHours(3),
            'hide_at' => $this->baseTime->copy()->subHour(),
        ]);

        $currentRoot = MenuItem::factory()->asMenu()->create([
            'display_at' => $this->baseTime->copy()->subHour(),
            'hide_at' => $this->baseTime->copy()->addHour(),
        ]);

        $rootsVisibleInPast = MenuItem::roots()->isVisibleAt($pastTime)->get();

        expect($rootsVisibleInPast->pluck('id'))->toContain($pastVisibleRoot->id);
        expect($rootsVisibleInPast->pluck('id'))->not->toContain($currentRoot->id);
    });

    test('temporal scopes work with query builder chaining', function () {
        $items = MenuItem::factory()->forMenu($this->menu)->count(10)->create([
            'display_at' => $this->baseTime->copy()->subHour(),
            'hide_at' => $this->baseTime->copy()->addHour(),
        ]);

        MenuItem::factory()->forMenu($this->menu)->count(5)->create([
            'display_at' => $this->baseTime->copy()->addHour(),
            'hide_at' => null,
        ]);

        $limitedVisible = MenuItem::visible()->limit(5)->get();
        $orderedVisible = MenuItem::visible()->orderBy('name')->get();
        $countVisible = MenuItem::visible()->count();

        expect($limitedVisible->count())->toBe(5);
        expect($orderedVisible->count())->toBe($countVisible);
        expect($countVisible)->toBeGreaterThanOrEqual(10);
    });
});

describe('Scope Performance and Edge Cases', function () {
    test('scopes handle large datasets efficiently', function () {
        // Create many items with different temporal settings
        MenuItem::factory()->forMenu($this->menu)->count(50)->create([
            'display_at' => $this->baseTime->copy()->subDay(),
            'hide_at' => $this->baseTime->copy()->addDay(),
        ]);

        MenuItem::factory()->forMenu($this->menu)->count(25)->create([
            'display_at' => $this->baseTime->copy()->addHour(),
            'hide_at' => null,
        ]);

        MenuItem::factory()->forMenu($this->menu)->count(25)->create([
            'display_at' => $this->baseTime->copy()->subDay(),
            'hide_at' => $this->baseTime->copy()->subHour(),
        ]);

        $startTime = microtime(true);
        $visibleItems = MenuItem::visible()->get();
        $queryTime = microtime(true) - $startTime;

        expect($visibleItems->count())->toBe(51); // 50 + 1 from beforeEach menu
        expect($queryTime)->toBeLessThan(1.0); // Should complete in under 1 second
    });

    test('scopes handle timezone-aware timestamps', function () {
        // Create item with UTC timestamps
        $utcDisplayTime = Carbon::create(2025, 1, 15, 10, 0, 0, 'UTC');
        $utcHideTime = Carbon::create(2025, 1, 15, 14, 0, 0, 'UTC');

        $item = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $utcDisplayTime,
            'hide_at' => $utcHideTime,
        ]);

        // Set current time to be within the window (12:00 UTC)
        Carbon::setTestNow(Carbon::create(2025, 1, 15, 12, 0, 0, 'UTC'));

        $visibleItems = MenuItem::visible()->get();

        expect($visibleItems->pluck('id'))->toContain($item->id);
    });

    test('scopes handle null values gracefully', function () {
        $allNullItem = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => null,
            'hide_at' => null,
        ]);

        $displayNullItem = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => null,
            'hide_at' => $this->baseTime->copy()->addHour(),
        ]);

        $hideNullItem = MenuItem::factory()->forMenu($this->menu)->create([
            'display_at' => $this->baseTime->copy()->subHour(),
            'hide_at' => null,
        ]);

        $visibleItems = MenuItem::visible()->get();

        expect($visibleItems->pluck('id'))->toContain($allNullItem->id);
        expect($visibleItems->pluck('id'))->toContain($displayNullItem->id);
        expect($visibleItems->pluck('id'))->toContain($hideNullItem->id);
    });

    test('scopes work with database transactions', function () {
        \DB::transaction(function () {
            $item = MenuItem::factory()->forMenu($this->menu)->create([
                'display_at' => $this->baseTime->copy()->subHour(),
                'hide_at' => $this->baseTime->copy()->addHour(),
            ]);

            $visibleItems = MenuItem::visible()->get();
            expect($visibleItems->pluck('id'))->toContain($item->id);
        });

        // Verify the item persists after transaction
        expect(MenuItem::visible()->count())->toBeGreaterThan(0);
    });
});
