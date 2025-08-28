<?php

use Illuminate\Support\Facades\Config;
use Skylark\Menus\Services\ResourceLinkService;

describe('ResourceLinkService', function () {
    beforeEach(function () {
        $this->service = new ResourceLinkService;

        // Set up test configuration
        Config::set('menus.resources', [
            'Product' => [
                'model' => 'Skylark\Menus\Models\MenuItem',
                'name_field' => 'name',
                'slug_field' => 'slug',
                'route_pattern' => '/products/{slug}',
            ],
            'Category' => [
                'model' => 'Skylark\Menus\Models\MenuItem',
                'name_field' => 'name',
                'slug_field' => 'slug',
                'route_pattern' => '/categories/{slug}',
            ],
        ]);
    });

    describe('getResourceTypes', function () {
        it('returns array of configured resource type keys', function () {
            $types = $this->service->getResourceTypes();

            expect($types)->toBe(['Product', 'Category']);
        });

        it('returns empty array when no resources configured', function () {
            Config::set('menus.resources', []);

            $types = $this->service->getResourceTypes();

            expect($types)->toBe([]);
        });
    });

    describe('getResourceConfiguration', function () {
        it('returns full resource configuration array', function () {
            $config = $this->service->getResourceConfiguration();

            expect($config)->toHaveKeys(['Product', 'Category']);
            expect($config['Product'])->toHaveKeys(['model', 'name_field', 'slug_field', 'route_pattern']);
        });

        it('returns empty array when configuration missing', function () {
            Config::set('menus', null);

            $config = $this->service->getResourceConfiguration();

            expect($config)->toBe([]);
        });
    });

    describe('getResourceConfig', function () {
        it('returns configuration for valid resource type', function () {
            $config = $this->service->getResourceConfig('Product');

            expect($config)->toBe([
                'model' => 'Skylark\Menus\Models\MenuItem',
                'name_field' => 'name',
                'slug_field' => 'slug',
                'route_pattern' => '/products/{slug}',
            ]);
        });

        it('throws exception for invalid resource type', function () {
            expect(fn () => $this->service->getResourceConfig('InvalidType'))
                ->toThrow(InvalidArgumentException::class, "Resource type 'InvalidType' is not configured.");
        });

        it('validates required configuration keys', function () {
            Config::set('menus.resources.Incomplete', [
                'model' => 'Some\\Model',
                'name_field' => 'name',
                // Missing slug_field and route_pattern
            ]);

            expect(fn () => $this->service->getResourceConfig('Incomplete'))
                ->toThrow(InvalidArgumentException::class);
        });

        it('validates model class exists', function () {
            Config::set('menus.resources.BadModel', [
                'model' => 'NonExistent\\Model\\Class',
                'name_field' => 'name',
                'slug_field' => 'slug',
                'route_pattern' => '/test/{slug}',
            ]);

            expect(fn () => $this->service->getResourceConfig('BadModel'))
                ->toThrow(InvalidArgumentException::class, "Model class 'NonExistent\\Model\\Class' does not exist for resource type 'BadModel'");
        });

        it('validates route pattern contains slug placeholder', function () {
            Config::set('menus.resources.BadRoute', [
                'model' => 'Skylark\Menus\Models\MenuItem',
                'name_field' => 'name',
                'slug_field' => 'slug',
                'route_pattern' => '/static-route',
            ]);

            expect(fn () => $this->service->getResourceConfig('BadRoute'))
                ->toThrow(InvalidArgumentException::class, "Route pattern for 'BadRoute' must contain {slug} placeholder");
        });
    });

    describe('generateUrl', function () {
        it('generates URL with slug replacement', function () {
            $url = $this->service->generateUrl('Product', 'awesome-product');

            expect($url)->toBe('/products/awesome-product');
        });

        it('generates URL for different resource types', function () {
            $productUrl = $this->service->generateUrl('Product', 'test-product');
            $categoryUrl = $this->service->generateUrl('Category', 'test-category');

            expect($productUrl)->toBe('/products/test-product');
            expect($categoryUrl)->toBe('/categories/test-category');
        });

        it('handles slugs with special characters', function () {
            $url = $this->service->generateUrl('Product', 'product-with-dashes_and_underscores');

            expect($url)->toBe('/products/product-with-dashes_and_underscores');
        });

        it('throws exception for invalid resource type', function () {
            expect(fn () => $this->service->generateUrl('InvalidType', 'some-slug'))
                ->toThrow(InvalidArgumentException::class);
        });
    });

    describe('validateResourceConfig', function () {
        it('passes validation for complete configuration', function () {
            $config = [
                'model' => 'Skylark\Menus\Models\MenuItem',
                'name_field' => 'name',
                'slug_field' => 'slug',
                'route_pattern' => '/products/{slug}',
            ];

            // Using reflection to test protected method
            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('validateResourceConfig');
            $method->setAccessible(true);

            $exception = null;
            try {
                $method->invokeArgs($this->service, ['Product', $config]);
            } catch (Exception $e) {
                $exception = $e;
            }

            expect($exception)->toBeNull();
        });

        it('fails validation for missing required keys', function () {
            $config = [
                'model' => 'Skylark\Menus\Models\MenuItem',
                // Missing other required keys
            ];

            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('validateResourceConfig');
            $method->setAccessible(true);

            expect(fn () => $method->invokeArgs($this->service, ['Product', $config]))
                ->toThrow(InvalidArgumentException::class);
        });

        it('fails validation for empty values', function () {
            $config = [
                'model' => '',
                'name_field' => 'name',
                'slug_field' => 'slug',
                'route_pattern' => '/products/{slug}',
            ];

            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('validateResourceConfig');
            $method->setAccessible(true);

            expect(fn () => $method->invokeArgs($this->service, ['Product', $config]))
                ->toThrow(InvalidArgumentException::class);
        });
    });

    describe('getModelInstance', function () {
        it('validates model inheritance from Eloquent Model', function () {
            // Test with a class that doesn't extend Model
            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('getModelInstance');
            $method->setAccessible(true);

            expect(fn () => $method->invokeArgs($this->service, [stdClass::class]))
                ->toThrow(InvalidArgumentException::class, "Class 'stdClass' must be an instance of Illuminate\\Database\\Eloquent\\Model");
        });

        it('can instantiate valid model classes', function () {
            $reflection = new ReflectionClass($this->service);
            $method = $reflection->getMethod('getModelInstance');
            $method->setAccessible(true);

            $result = $method->invokeArgs($this->service, ['Skylark\Menus\Models\MenuItem']);

            expect($result)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class);
        });
    });
});

describe('ResourceLinkService Integration', function () {
    beforeEach(function () {
        $this->service = new ResourceLinkService;
    });

    describe('method validation', function () {
        it('has searchResources method', function () {
            expect(method_exists($this->service, 'searchResources'))->toBeTrue();
        });

        it('has getResource method', function () {
            expect(method_exists($this->service, 'getResource'))->toBeTrue();
        });

        it('throws exception for invalid resource type in searchResources', function () {
            expect(fn () => $this->service->searchResources('InvalidType', 'test'))
                ->toThrow(InvalidArgumentException::class);
        });

        it('throws exception for invalid resource type in getResource', function () {
            expect(fn () => $this->service->getResource('InvalidType', 1))
                ->toThrow(InvalidArgumentException::class);
        });
    });
});
