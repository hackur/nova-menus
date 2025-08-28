<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Skylark\Menus\Models\Menu;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = \App\Models\User::factory()->create();
    // Create super-admin role if it doesn't exist for Nova access
    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
    $user->assignRole($role);
    $this->actingAs($user);
});

it('can access the menu index page', function () {
    $response = $this->get('/admin/menus');

    $response->assertStatus(200);
});

it('has the edit route registered', function () {
    // Test that the route exists in Laravel's route collection
    $routeCollection = app('router')->getRoutes();
    $routeNames = [];

    foreach ($routeCollection as $route) {
        if (str_contains($route->uri(), 'menus') && str_contains($route->uri(), 'edit')) {
            $routeNames[] = $route->uri();
        }
    }

    expect($routeNames)->toContain('admin/menus/{menuId}/edit');
});

it('can create a menu for testing', function () {
    $menu = Menu::factory()->create([
        'name' => 'Test Menu',
        'slug' => 'test-menu',
    ]);

    expect($menu)->toBeInstanceOf(Menu::class);
    expect($menu->name)->toBe('Test Menu');
    expect($menu->slug)->toBe('test-menu');
});
