<?php

use Skylark\Menus\Menus;

describe('Menu Tool End-to-End Tests', function () {
    beforeEach(function () {
        config(['menus.enabled' => true]);

        // Create a user for authentication
        $this->user = \App\Models\User::factory()->create();
        // Create super-admin role if it doesn't exist for Nova access
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
        $this->user->assignRole($role);
    });

    it('can access menu tool page through Nova dashboard', function () {
        $this->actingAs($this->user);

        $response = $this->get('/admin/menus');
        expect($response->status())->toBe(200);

        // Note: Inertia component assertions skipped in test environment
        // since frontend assets aren't available during backend testing
    });

    it('redirects unauthenticated users to login', function () {
        $response = $this->get('/admin/menus');

        // Should redirect to login or return 401/403
        expect($response->status())->toBeIn([302, 401, 403]);
    });

    it('serves API endpoints with proper authentication', function () {
        $this->actingAs($this->user);

        // Test the menus API endpoint
        $response = $this->getJson('/nova-vendor/menus/menus');
        expect($response->status())->toBe(200);

        // Response should have the expected structure
        $response->assertJsonStructure([
            'success',
            'data',
            'message',
        ]);
    });

    it('requires authentication for API endpoints', function () {
        // Test without authentication
        $response = $this->getJson('/nova-vendor/menus/menus');
        expect($response->status())->toBeIn([401, 403]);
    });

    it('can create a menu via API', function () {
        $this->actingAs($this->user);

        $menuData = [
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'max_depth' => 5,
            'is_active' => true,
        ];

        $response = $this->postJson('/nova-vendor/menus/menus', $menuData);
        expect($response->status())->toBe(201);

        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'slug',
                'max_depth',
                'is_active',
            ],
            'message',
        ]);

        // Verify it was created in the database
        $this->assertDatabaseHas('menus', [
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'max_depth' => 5,
            'is_active' => true,
        ]);
    });

    it('validates menu creation data', function () {
        $this->actingAs($this->user);

        // Test with empty name
        $response = $this->postJson('/nova-vendor/menus/menus', []);
        expect($response->status())->toBe(422);

        $response->assertJsonStructure([
            'success',
            'message',
            'errors',
        ]);
    });

    it('can update a menu via API', function () {
        $this->actingAs($this->user);

        // Create a menu first
        $menu = \Skylark\Menus\Models\Menu::factory()->create([
            'name' => 'Original Name',
            'slug' => 'original-slug',
        ]);

        $updateData = [
            'name' => 'Updated Menu Name',
            'slug' => 'updated-slug',
        ];

        $response = $this->putJson("/nova-vendor/menus/menus/{$menu->id}", $updateData);
        expect($response->status())->toBe(200);

        // Verify it was updated in the database
        $this->assertDatabaseHas('menus', [
            'id' => $menu->id,
            'name' => 'Updated Menu Name',
            'slug' => 'updated-slug',
        ]);
    });

    it('can delete a menu via API', function () {
        $this->actingAs($this->user);

        // Create a menu first
        $menu = \Skylark\Menus\Models\Menu::factory()->create();

        $response = $this->deleteJson("/nova-vendor/menus/menus/{$menu->id}");
        expect($response->status())->toBe(200);

        // Verify it was deleted from the database
        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    });

    it('handles non-existent menu gracefully', function () {
        $this->actingAs($this->user);

        // Try to get a non-existent menu
        $response = $this->getJson('/nova-vendor/menus/menus/999999');
        expect($response->status())->toBe(404);

        $response->assertJson([
            'success' => false,
            'message' => 'Menu not found',
        ]);
    });
});
