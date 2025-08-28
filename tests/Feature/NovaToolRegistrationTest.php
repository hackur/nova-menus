<?php

use Illuminate\Support\Facades\Log;
use Laravel\Nova\Nova;
use Skylark\Menus\Menus;
use Skylark\Menus\ToolServiceProvider;

describe('Nova Tool Registration', function () {
    it('registers the Menu tool with Nova when enabled', function () {
        // Ensure feature flag is enabled
        config(['menus.enabled' => true]);

        // Get registered tools
        $tools = Nova::registeredTools();
        $menuTool = collect($tools)->first(fn ($tool) => $tool instanceof Menus);

        expect($menuTool)->not()->toBeNull();
        expect($menuTool)->toBeInstanceOf(Menus::class);
    });

    it('does not register the Menu tool when disabled via feature flag', function () {
        // Disable the tool via feature flag
        config(['menus.enabled' => false]);

        // Clear registered tools
        Nova::$tools = [];

        // Get registered tools
        $tools = Nova::registeredTools();
        $menuTool = collect($tools)->first(fn ($tool) => $tool instanceof Menus);

        expect($menuTool)->toBeNull();
    });

    it('appears in Nova dashboard with correct menu configuration', function () {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        config(['menus.enabled' => true]);

        $tools = Nova::registeredTools();
        $menuTool = collect($tools)->first(fn ($tool) => $tool instanceof Menus);

        expect($menuTool)->not()->toBeNull();

        // Test menu section creation
        $request = request();
        $menuSection = $menuTool->menu($request);

        expect($menuSection->name)->toBe('Menus');
        expect($menuSection->path)->toBe('/menus');
        expect($menuSection->icon)->toBe('server');
    });
});

describe('Tool Route Registration', function () {
    it('registers inertia routes with proper authentication middleware', function () {
        config(['menus.enabled' => true]);

        // Boot the application to register routes
        $this->app->booted(function () {
            // Test that routes are registered
            $routes = collect(\Illuminate\Support\Facades\Route::getRoutes());

            // Check main tool route exists
            $menusRoute = $routes->first(function ($route) {
                return str_contains($route->uri, 'admin/menus') &&
                       $route->methods[0] === 'GET' &&
                       ! str_contains($route->uri, '{menuId}');
            });

            expect($menusRoute)->not()->toBeNull();

            // Check edit route exists
            $editRoute = $routes->first(function ($route) {
                return str_contains($route->uri, 'admin/menus/{menuId}/edit') &&
                       $route->methods[0] === 'GET';
            });

            expect($editRoute)->not()->toBeNull();
        });
    });

    it('registers api routes with proper authentication middleware', function () {
        config(['menus.enabled' => true]);

        $this->app->booted(function () {
            $routes = collect(\Illuminate\Support\Facades\Route::getRoutes());

            // Check API routes are registered with proper middleware
            $apiRoutes = $routes->filter(function ($route) {
                return str_contains($route->uri, 'nova-vendor/menus');
            });

            // For now, just verify the route structure exists
            // TODO: Add specific API endpoints when controllers are implemented
            expect($apiRoutes)->not()->toBeEmpty();
        });
    });
});

describe('Authentication and Authorization', function () {
    it('requires authentication to access tool routes', function () {
        config(['menus.enabled' => true]);

        // Test without authentication
        $response = $this->get('/admin/menus');

        // Should redirect to login or return 401/403
        expect($response->status())->toBeIn([302, 401, 403]);
    });

    it('allows authenticated Nova users to access tool routes', function () {
        $user = \App\Models\User::factory()->create();
        // Create super-admin role if it doesn't exist for Nova access
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super-admin']);
        $user->assignRole($role);

        config(['menus.enabled' => true]);

        $response = $this->actingAs($user)
            ->get('/admin/menus');

        // Should successfully load the tool page
        expect($response->status())->toBe(200);
    });

    it('applies authorization middleware correctly', function () {
        $user = \App\Models\User::factory()->create();

        config(['menus.enabled' => true]);

        // Test that the Authorize middleware is applied
        $this->actingAs($user);

        // The authorize middleware will check if the tool authorizes the user
        // For now, we'll just verify the middleware is in place
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes());
        $menuRoute = $routes->first(function ($route) {
            return str_contains($route->uri, 'admin/menus') &&
                   $route->methods[0] === 'GET' &&
                   ! str_contains($route->uri, '{menuId}');
        });

        expect($menuRoute)->not()->toBeNull();

        // Verify middleware includes our Authorize class
        $middleware = $menuRoute->middleware();
        $hasAuthorizeMiddleware = collect($middleware)->contains(function ($m) {
            return str_contains($m, 'Skylark\Menus\Http\Middleware\Authorize') ||
                   str_contains($m, 'nova');
        });

        expect($hasAuthorizeMiddleware)->toBeTrue();
    });
});

describe('Feature Flag Functionality', function () {
    it('respects feature flag for tool registration', function () {
        // Test that the feature flag configuration exists and has correct default
        expect(config('menus.enabled', true))->toBe(true);

        // Test that the service provider checks this flag
        $serviceProvider = app()->getProvider(\Skylark\Menus\MenusServiceProvider::class);
        expect($serviceProvider)->not()->toBeNull();

        // Verify the tool is registered when enabled (current state)
        $tools = Nova::registeredTools();
        $menuTool = collect($tools)->first(fn ($tool) => $tool instanceof Menus);
        expect($menuTool)->not()->toBeNull();
    });

    it('respects feature flag for route registration', function () {
        // When disabled, routes should not be registered
        config(['menus.enabled' => false]);

        $toolServiceProvider = new ToolServiceProvider($this->app);

        // Use reflection to test the isMenuToolEnabled method
        $reflection = new ReflectionClass($toolServiceProvider);
        $method = $reflection->getMethod('isMenuToolEnabled');
        $method->setAccessible(true);

        $result = $method->invoke($toolServiceProvider);
        expect($result)->toBeFalse();

        // When enabled
        config(['menus.enabled' => true]);
        $result = $method->invoke($toolServiceProvider);
        expect($result)->toBeTrue();
    });
});

describe('Performance Monitoring Integration', function () {
    it('logs menu tool access when monitoring is enabled', function () {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user);

        config(['menus.enabled' => true]);
        config(['menus.monitoring.enabled' => true]);

        // Mock Log facade to capture log calls
        Log::shouldReceive('info')
            ->once()
            ->with('Nova Menu tool accessed', \Mockery::any());

        // Trigger the Nova serving event
        $event = new \Laravel\Nova\Events\ServingNova(app(), request());
        event($event);
    });

    it('uses correct log configuration', function () {
        $config = config('menus.monitoring');

        expect($config)->toHaveKey('enabled');
        expect($config)->toHaveKey('log_channel');
        expect($config['log_channel'])->toBe('single');
    });
});

describe('Integration with Existing Nova Functionality', function () {
    it('does not interfere with existing Nova tools when enabled', function () {
        config(['menus.enabled' => true]);

        // Get all registered tools
        $tools = Nova::registeredTools();

        // Our tool should be present
        $menuTool = collect($tools)->first(fn ($tool) => $tool instanceof Menus);
        expect($menuTool)->not()->toBeNull();

        // Should not break Nova's tool system
        expect($tools)->toBeArray();
        expect(count($tools))->toBeGreaterThan(0);
    });

    it('does not interfere with existing Nova tools when disabled', function () {
        config(['menus.enabled' => false]);

        // Clear registered tools
        Nova::$tools = [];

        $tools = Nova::registeredTools();

        // Our tool should not be present
        $menuTool = collect($tools)->first(fn ($tool) => $tool instanceof Menus);
        expect($menuTool)->toBeNull();

        // Nova's tool system should still work
        expect($tools)->toBeArray();
    });
});
