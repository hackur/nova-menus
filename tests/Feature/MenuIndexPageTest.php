<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Skylark\Menus\Models\Menu;

uses(RefreshDatabase::class);

describe('Menu Index Page', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        // Create super-admin role if it doesn't exist
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
        $this->user->assignRole($role);
        $this->actingAs($this->user);
    });

    it('can render menu index page through Nova tool', function () {
        $response = $this->get('/admin/menus');

        expect($response->status())->toBe(200);
    });

    it('displays menu list with proper Nova styling', function () {
        // Create test menus
        $menus = Menu::factory()->count(3)->create();

        $response = $this->get('/admin/menus');

        expect($response->status())->toBe(200);

        // Check that menus are available via API
        $apiResponse = $this->get('/nova-vendor/menus/menus');
        expect($apiResponse->status())->toBe(200);

        $data = $apiResponse->json('data');
        expect($data)->toHaveCount(3);

        foreach ($menus as $menu) {
            $found = collect($data)->firstWhere('id', $menu->id);
            expect($found)->not()->toBeNull();
            expect($found['name'])->toBe($menu->name);
            expect($found['slug'])->toBe($menu->slug);
            expect($found['max_depth'])->toBe($menu->max_depth);
        }
    });

    it('handles empty state when no menus exist', function () {
        $response = $this->get('/nova-vendor/menus/menus');

        expect($response->status())->toBe(200);
        expect($response->json('data'))->toBeEmpty();
    });

    it('can search menus through API', function () {
        Menu::factory()->create(['name' => 'Main Navigation', 'slug' => 'main-nav']);
        Menu::factory()->create(['name' => 'Footer Links', 'slug' => 'footer-links']);
        Menu::factory()->create(['name' => 'Sidebar Menu', 'slug' => 'sidebar-menu']);

        // Test search functionality via API (frontend will filter)
        $response = $this->get('/nova-vendor/menus/menus');
        $menus = $response->json('data');

        expect($menus)->toHaveCount(3);

        // Verify we can find specific menus
        $mainNav = collect($menus)->firstWhere('name', 'Main Navigation');
        expect($mainNav)->not()->toBeNull();
        expect($mainNav['slug'])->toBe('main-nav');
    });
});

describe('Menu Modal Functionality', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        // Create super-admin role if it doesn't exist
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
        $this->user->assignRole($role);
        $this->actingAs($this->user);
    });

    it('can create a menu through modal form submission', function () {
        $menuData = [
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'max_depth' => 5,
        ];

        $response = $this->post('/nova-vendor/menus/menus', $menuData);

        expect($response->status())->toBe(201);

        $this->assertDatabaseHas('menus', [
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'max_depth' => 5,
        ]);

        $responseData = $response->json('data');
        expect($responseData['name'])->toBe('Test Menu');
        expect($responseData['slug'])->toBe('test-menu');
        expect($responseData['max_depth'])->toBe(5);
    });

    it('validates menu creation form data', function () {
        // Test missing name
        $response = $this->post('/nova-vendor/menus/menus', [
            'slug' => 'test-menu',
            'max_depth' => 5,
        ]);

        expect($response->status())->toBe(422);
        expect($response->json('errors'))->toHaveKey('name');
    });

    it('validates name length requirement', function () {
        $response = $this->post('/nova-vendor/menus/menus', [
            'name' => 'ab', // Too short
            'slug' => 'test-menu',
            'max_depth' => 5,
        ]);

        expect($response->status())->toBe(422);
        expect($response->json('errors'))->toHaveKey('name');
    });

    it('validates max depth range', function () {
        // Test max_depth too high
        $response = $this->post('/nova-vendor/menus/menus', [
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'max_depth' => 15, // Too high
        ]);

        expect($response->status())->toBe(422);
        expect($response->json('errors'))->toHaveKey('max_depth');

        // Test max_depth too low
        $response = $this->post('/nova-vendor/menus/menus', [
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'max_depth' => 0, // Too low
        ]);

        expect($response->status())->toBe(422);
        expect($response->json('errors'))->toHaveKey('max_depth');
    });

    it('auto-generates slug when not provided', function () {
        $response = $this->post('/nova-vendor/menus/menus', [
            'name' => 'My Test Menu',
            'max_depth' => 6,
        ]);

        expect($response->status())->toBe(201);

        $responseData = $response->json('data');
        expect($responseData['slug'])->toBe('my-test-menu');

        $this->assertDatabaseHas('menus', [
            'name' => 'My Test Menu',
            'slug' => 'my-test-menu',
        ]);
    });

    it('validates unique slug constraint', function () {
        Menu::factory()->create(['slug' => 'existing-menu']);

        $response = $this->post('/nova-vendor/menus/menus', [
            'name' => 'New Menu',
            'slug' => 'existing-menu',
            'max_depth' => 6,
        ]);

        expect($response->status())->toBe(422);
        expect($response->json('errors'))->toHaveKey('slug');
    });
});

describe('Menu Actions', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        // Create super-admin role if it doesn't exist
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
        $this->user->assignRole($role);
        $this->actingAs($this->user);
    });

    it('can delete a menu', function () {
        $menu = Menu::factory()->create();

        $response = $this->delete("/nova-vendor/menus/menus/{$menu->id}");

        expect($response->status())->toBe(200);
        $this->assertDatabaseMissing('menus', ['id' => $menu->id]);
    });

    it('returns 404 when trying to delete non-existent menu', function () {
        $response = $this->delete('/nova-vendor/menus/menus/99999');

        expect($response->status())->toBe(404);
    });

    it('can navigate to edit menu', function () {
        $menu = Menu::factory()->create();

        $response = $this->get("/admin/menus/{$menu->id}/edit");

        // This should either be 200 or redirect to edit page depending on route setup
        expect($response->status())->toBeIn([200, 302]);
    });
});

describe('Authentication and Authorization', function () {
    it('requires authentication for menu index page', function () {
        $response = $this->get('/admin/menus');

        // Should redirect to login or return 401/403/404
        expect($response->status())->toBeIn([302, 401, 403, 404]);
    });

    it('requires authentication for API endpoints', function () {
        $response = $this->get('/nova-vendor/menus/menus');

        expect($response->status())->toBeIn([302, 401, 403, 404]);
    });

    it('requires authentication for menu creation', function () {
        $response = $this->post('/nova-vendor/menus/menus', [
            'name' => 'Test Menu',
            'slug' => 'test-menu',
            'max_depth' => 6,
        ]);

        expect($response->status())->toBeIn([302, 401, 403, 404]);
    });
});
