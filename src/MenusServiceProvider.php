<?php

namespace Skylark\Menus;

use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Nova;
use Skylark\Menus\Services\ResourceLinkService;

class MenusServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publish configuration file
        $this->publishes([
            __DIR__.'/../config/menus.php' => config_path('menus.php'),
        ], 'config');

        // Ensure migrations are published to main database/migrations for tests
        if ($this->app->runningUnitTests()) {
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'migrations');
        }

        // Register model factories for Laravel 12
        if (method_exists($this->app, 'make') && class_exists('Illuminate\\Database\\Eloquent\\Factories\\Factory')) {
            // Laravel 12+ factory registration approach
            // Factories are auto-discovered, no explicit loading needed
        }

        // Only register the Nova tool if it's enabled
        if (config('menus.enabled', true)) {
            Nova::tools([
                new Menus,
            ]);
        }
    }

    public function register(): void
    {
        // Merge package configuration
        $this->mergeConfigFrom(__DIR__.'/../config/menus.php', 'menus');

        // Register services
        $this->app->singleton(ResourceLinkService::class);
    }
}
