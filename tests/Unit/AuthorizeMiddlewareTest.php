<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Nova\Tool;
use Skylark\Menus\Http\Middleware\Authorize;
use Skylark\Menus\Menus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->middleware = new Authorize;
    $this->request = new Request;
});

describe('Authorize::matchesTool', function () {
    test('returns true for Menus tool', function () {
        $menusTool = new Menus;

        $result = $this->middleware->matchesTool($menusTool);

        expect($result)->toBeTrue();
    });

    test('returns false for other tools', function () {
        $otherTool = $this->mock(Tool::class);

        $result = $this->middleware->matchesTool($otherTool);

        expect($result)->toBeFalse();
    });
});

describe('Authorize Middleware basic functionality', function () {
    test('can be instantiated without errors', function () {
        expect($this->middleware)->toBeInstanceOf(Authorize::class);
    });

    test('matchesTool method exists and is callable', function () {
        expect(method_exists($this->middleware, 'matchesTool'))->toBeTrue();
        expect(is_callable([$this->middleware, 'matchesTool']))->toBeTrue();
    });

    test('handle method exists and is callable', function () {
        expect(method_exists($this->middleware, 'handle'))->toBeTrue();
        expect(is_callable([$this->middleware, 'handle']))->toBeTrue();
    });
});
