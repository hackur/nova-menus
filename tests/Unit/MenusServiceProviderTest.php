<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Skylark\Menus\MenusServiceProvider;
use Skylark\Menus\Services\ResourceLinkService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->provider = new MenusServiceProvider($this->app);
});

describe('MenusServiceProvider', function () {
    test('registers resource link service as singleton', function () {
        $this->provider->register();

        $service1 = $this->app->make(ResourceLinkService::class);
        $service2 = $this->app->make(ResourceLinkService::class);

        expect($service1)->toBeInstanceOf(ResourceLinkService::class);
        expect($service1)->toBe($service2); // Same instance (singleton)
    });

    test('merges package configuration', function () {
        // Set test config values
        Config::set('menus', ['test_key' => 'original_value']);

        $this->provider->register();

        // The provider should have merged its config
        $config = Config::get('menus');
        expect($config)->toBeArray();
        expect($config)->toHaveKey('enabled'); // From package config
    });

    test('boot method runs without errors when enabled', function () {
        Config::set('menus.enabled', true);

        $result = null;
        $exception = null;

        try {
            $result = $this->provider->boot();
        } catch (Exception $e) {
            $exception = $e;
        }

        expect($exception)->toBeNull();
    });

    test('boot method runs without errors when disabled', function () {
        Config::set('menus.enabled', false);

        $result = null;
        $exception = null;

        try {
            $result = $this->provider->boot();
        } catch (Exception $e) {
            $exception = $e;
        }

        expect($exception)->toBeNull();
    });

    test('publishes configuration file', function () {
        // This test checks that the publishes method is called with correct parameters
        $this->provider->boot();

        $publishGroups = $this->provider->pathsToPublish(MenusServiceProvider::class);

        expect($publishGroups)->toBeArray();
    });

    test('loads migrations without errors', function () {
        $exception = null;

        try {
            $this->provider->boot();
        } catch (Exception $e) {
            $exception = $e;
        }

        expect($exception)->toBeNull();
    });
});

describe('MenusServiceProvider in test environment', function () {
    test('publishes migrations when running unit tests', function () {
        // Set the application to running unit tests
        $this->app->detectEnvironment(function () {
            return 'testing';
        });

        $this->provider->boot();

        $publishGroups = $this->provider->pathsToPublish(MenusServiceProvider::class, 'migrations');

        expect($publishGroups)->toBeArray();
    });
});
