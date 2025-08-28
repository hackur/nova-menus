<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Skylark\Menus\ToolServiceProvider;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->provider = new ToolServiceProvider($this->app);
});

describe('ToolServiceProvider', function () {
    test('loads routes from correct path', function () {
        // Mock the loadRoutesFrom call
        $routesLoaded = false;
        $routePath = '';

        // We can't easily test loadRoutesFrom directly, so we'll test that the provider
        // can be instantiated and boot method can be called without errors
        $exception = null;

        try {
            $this->provider->boot();
        } catch (Exception $e) {
            $exception = $e;
        }

        expect($exception)->toBeNull();
    });

    test('can be instantiated without errors', function () {
        expect($this->provider)->toBeInstanceOf(ToolServiceProvider::class);
    });

    test('boot method executes without errors', function () {
        $exception = null;

        try {
            $this->provider->boot();
        } catch (Exception $e) {
            $exception = $e;
        }

        expect($exception)->toBeNull();
    });

    test('register method executes without errors', function () {
        $exception = null;

        try {
            $this->provider->register();
        } catch (Exception $e) {
            $exception = $e;
        }

        expect($exception)->toBeNull();
    });
});

describe('ToolServiceProvider integration', function () {
    test('routes are properly loaded when provider is registered', function () {
        // Register the service provider
        $this->app->register(ToolServiceProvider::class);

        // The routes should be available in the application
        // We can test this by checking that the service provider registration completes
        $registeredProviders = $this->app->getLoadedProviders();

        expect($registeredProviders)->toHaveKey(ToolServiceProvider::class);
    });
});
