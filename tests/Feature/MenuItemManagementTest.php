<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Skylark\Menus\Models\Menu;
use Skylark\Menus\Models\MenuItem;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a test user with super-admin role for Nova access
    $this->user = \App\Models\User::factory()->create();
    // Create super-admin role if it doesn't exist
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
    $this->user->assignRole($role);
    $this->actingAs($this->user);
});

describe('Menu Item CRUD Operations', function () {
    it('can create a menu item with custom URL', function () {
        $menu = Menu::factory()->create(['name' => 'Test Menu']);

        $response = $this->postJson('/nova-vendor/menus/menu-items', [
            'menu_id' => $menu->id,
            'name' => 'Test Item',
            'custom_url' => '/test-page',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Menu item created successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'menu_id',
                    'name',
                    'custom_url',
                    'is_active',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertDatabaseHas('menu_items', [
            'menu_id' => $menu->id,
            'name' => 'Test Item',
            'custom_url' => '/test-page',
            'is_active' => true,
        ]);
    });

    it('can create a menu item with resource link', function () {
        $menu = Menu::factory()->create(['name' => 'Resource Menu']);

        $response = $this->postJson('/nova-vendor/menus/menu-items', [
            'menu_id' => $menu->id,
            'name' => 'User Resource',
            'resource_type' => 'User',
            'resource_id' => 123,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Menu item created successfully',
            ]);

        $this->assertDatabaseHas('menu_items', [
            'menu_id' => $menu->id,
            'name' => 'User Resource',
            'resource_type' => 'User',
            'resource_id' => 123,
            'custom_url' => null,
        ]);
    });

    it('can create a menu item with temporal visibility', function () {
        $menu = Menu::factory()->create(['name' => 'Temporal Menu']);
        $displayAt = now()->addDays(1);
        $hideAt = now()->addDays(7);

        $response = $this->postJson('/nova-vendor/menus/menu-items', [
            'menu_id' => $menu->id,
            'name' => 'Temporal Item',
            'custom_url' => '/temporal-page',
            'display_at' => $displayAt->toISOString(),
            'hide_at' => $hideAt->toISOString(),
            'is_active' => true,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('menu_items', [
            'menu_id' => $menu->id,
            'name' => 'Temporal Item',
            'custom_url' => '/temporal-page',
        ]);

        $item = MenuItem::where('name', 'Temporal Item')->first();
        expect($item->display_at)->not->toBeNull();
        expect($item->hide_at)->not->toBeNull();
        expect($item->display_at->format('Y-m-d'))->toEqual($displayAt->format('Y-m-d'));
        expect($item->hide_at->format('Y-m-d'))->toEqual($hideAt->format('Y-m-d'));
    });

    it('can update a menu item', function () {
        $menu = Menu::factory()->create(['name' => 'Update Test Menu']);
        $menuItem = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'name' => 'Original Name',
            'custom_url' => '/original',
        ]);

        $response = $this->putJson("/nova-vendor/menus/menu-items/{$menuItem->id}", [
            'name' => 'Updated Name',
            'custom_url' => '/updated-page',
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Menu item updated successfully',
            ]);

        $this->assertDatabaseHas('menu_items', [
            'id' => $menuItem->id,
            'name' => 'Updated Name',
            'custom_url' => '/updated-page',
            'is_active' => false,
        ]);
    });

    it('can delete a menu item', function () {
        $menu = Menu::factory()->create(['name' => 'Delete Test Menu']);
        $menuItem = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'name' => 'Item To Delete',
        ]);

        $response = $this->deleteJson("/nova-vendor/menus/menu-items/{$menuItem->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Menu item deleted successfully',
            ]);

        $this->assertDatabaseMissing('menu_items', [
            'id' => $menuItem->id,
        ]);
    });

    it('deletes child menu items recursively when parent is deleted', function () {
        $menu = Menu::factory()->create(['name' => 'Hierarchical Menu']);
        $parentItem = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'name' => 'Parent Item',
        ]);
        $childItem = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'name' => 'Child Item',
            'parent_id' => $parentItem->id,
        ]);
        $grandchildItem = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'name' => 'Grandchild Item',
            'parent_id' => $childItem->id,
        ]);

        $response = $this->deleteJson("/nova-vendor/menus/menu-items/{$parentItem->id}");

        $response->assertStatus(200);

        // All items should be deleted
        $this->assertDatabaseMissing('menu_items', ['id' => $parentItem->id]);
        $this->assertDatabaseMissing('menu_items', ['id' => $childItem->id]);
        $this->assertDatabaseMissing('menu_items', ['id' => $grandchildItem->id]);
    });
});

describe('Menu Items API Endpoint', function () {
    it('can retrieve menu items for a specific menu', function () {
        $menu = Menu::factory()->create(['name' => 'API Test Menu']);
        $item1 = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'name' => 'First Item',
            'position' => 1,
        ]);
        $item2 = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'name' => 'Second Item',
            'position' => 2,
        ]);

        $response = $this->getJson("/nova-vendor/menus/menus/{$menu->id}/items");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Menu items retrieved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'menu_id',
                        'name',
                        'custom_url',
                        'resource_type',
                        'resource_id',
                        'position',
                        'is_active',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        $responseData = $response->json('data');
        expect(count($responseData))->toBe(2);

        // Verify items are ordered by position
        expect($responseData[0]['name'])->toBe('First Item');
        expect($responseData[1]['name'])->toBe('Second Item');
    });

    it('returns empty array for menu with no items', function () {
        $menu = Menu::factory()->create(['name' => 'Empty Menu']);

        $response = $this->getJson("/nova-vendor/menus/menus/{$menu->id}/items");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [],
            ]);
    });

    it('returns 404 for non-existent menu', function () {
        $response = $this->getJson('/nova-vendor/menus/menus/999999/items');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Menu not found',
            ]);
    });
});

describe('Form Validation', function () {
    it('validates required fields for menu item creation', function () {
        $menu = Menu::factory()->create(['name' => 'Validation Menu']);

        $response = $this->postJson('/nova-vendor/menus/menu-items', [
            'menu_id' => $menu->id,
            // Missing required 'name' field
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonStructure([
                'errors' => [
                    'name',
                ],
            ]);
    });

    it('validates custom_url format accepts both URIs and URLs', function () {
        $menu = Menu::factory()->create(['name' => 'URL Validation Menu']);

        // Test relative URI
        $response = $this->postJson('/nova-vendor/menus/menu-items', [
            'menu_id' => $menu->id,
            'name' => 'Relative URI Item',
            'custom_url' => '/home',
            'is_active' => true,
        ]);

        $response->assertStatus(201);

        // Test absolute URL
        $response = $this->postJson('/nova-vendor/menus/menu-items', [
            'menu_id' => $menu->id,
            'name' => 'Absolute URL Item',
            'custom_url' => 'https://example.com',
            'is_active' => true,
        ]);

        $response->assertStatus(201);

        // Test hash link
        $response = $this->postJson('/nova-vendor/menus/menu-items', [
            'menu_id' => $menu->id,
            'name' => 'Hash Link Item',
            'custom_url' => '#section1',
            'is_active' => true,
        ]);

        $response->assertStatus(201);
    });

    it('validates temporal visibility dates', function () {
        $menu = Menu::factory()->create(['name' => 'Temporal Validation Menu']);

        $displayAt = now()->addDays(7);
        $hideAt = now()->addDays(3); // Hide date before display date - should fail

        $response = $this->postJson('/nova-vendor/menus/menu-items', [
            'menu_id' => $menu->id,
            'name' => 'Invalid Temporal Item',
            'custom_url' => '/temporal-invalid',
            'display_at' => $displayAt->toISOString(),
            'hide_at' => $hideAt->toISOString(),
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'hide_at',
                ],
            ]);
    });

    it('prevents circular parent relationships', function () {
        $menu = Menu::factory()->create(['name' => 'Circular Prevention Menu']);
        $menuItem = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'name' => 'Self Parent Test',
        ]);

        $response = $this->putJson("/nova-vendor/menus/menu-items/{$menuItem->id}", [
            'parent_id' => $menuItem->id, // Trying to be its own parent
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'errors' => [
                    'parent_id',
                ],
            ]);
    });
});

describe('Parent-Child Relationships', function () {
    it('can create menu items with parent-child relationships', function () {
        $menu = Menu::factory()->create(['name' => 'Hierarchical Test Menu']);
        $parentItem = MenuItem::factory()->create([
            'menu_id' => $menu->id,
            'name' => 'Parent Item',
        ]);

        $response = $this->postJson('/nova-vendor/menus/menu-items', [
            'menu_id' => $menu->id,
            'name' => 'Child Item',
            'custom_url' => '/child-page',
            'parent_id' => $parentItem->id,
            'is_active' => true,
        ]);

        $response->assertStatus(201);

        $childItem = MenuItem::where('name', 'Child Item')->first();
        expect($childItem->parent_id)->toBe($parentItem->id);

        // Verify the relationship works
        expect($parentItem->children->count())->toBe(1);
        expect($parentItem->children->first()->name)->toBe('Child Item');
    });

    it('automatically sets position for menu items', function () {
        $menu = Menu::factory()->create(['name' => 'Position Test Menu']);

        // Create first item
        $response1 = $this->postJson('/nova-vendor/menus/menu-items', [
            'menu_id' => $menu->id,
            'name' => 'First Item',
            'custom_url' => '/first',
            'is_active' => true,
        ]);

        // Create second item
        $response2 = $this->postJson('/nova-vendor/menus/menu-items', [
            'menu_id' => $menu->id,
            'name' => 'Second Item',
            'custom_url' => '/second',
            'is_active' => true,
        ]);

        $response1->assertStatus(201);
        $response2->assertStatus(201);

        $firstItem = MenuItem::where('name', 'First Item')->first();
        $secondItem = MenuItem::where('name', 'Second Item')->first();

        expect($firstItem->position)->toBe(0);
        expect($secondItem->position)->toBe(1);
    });
});
