<?php

namespace Skylark\Menus;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;
use Skylark\Menus\Http\Middleware\Authorize;

class ToolServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only register routes and Nova integration if tool is enabled
        if ($this->isMenuToolEnabled()) {
            $this->app->booted(function () {
                $this->routes();
            });

            Nova::serving(function (ServingNova $event) {
                // Log menu tool access for performance monitoring
                Log::info('Nova Menu tool accessed', [
                    'user_id' => auth()->id(),
                    'timestamp' => now(),
                    'context' => 'nova_serving',
                ]);
            });
        }
    }

    /**
     * Register the tool's routes.
     */
    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Nova::router(['nova', 'nova.auth', Authorize::class], 'menus')
            ->group(__DIR__.'/../routes/inertia.php');

        Route::middleware(['nova', 'nova.auth', Authorize::class])
            ->prefix('nova-vendor/menus')
            ->group(__DIR__.'/../routes/api.php');

        // Public API routes for frontend consumption
        Route::middleware(['api', 'throttle:60,1'])
            ->prefix('api')
            ->group(__DIR__.'/../routes/public-api.php');
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register configuration for feature flag
        $this->mergeConfigFrom(
            __DIR__.'/../config/menus.php', 'menus'
        );
    }

    /**
     * Check if the menu tool is enabled via configuration.
     */
    protected function isMenuToolEnabled(): bool
    {
        return config('menus.enabled', true);
    }
}
