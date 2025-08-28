<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Skylark\Menus\Services\ResourceLinkService;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('migrate', ['--force' => true]);
    $this->service = new ResourceLinkService();
});

describe('ResourceLinkService SoftDelete Handling', function () {
    test('can handle models without SoftDeletes trait', function () {
        // Mock a model class that doesn't use SoftDeletes
        $modelClass = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'test_resources';
            protected $fillable = ['name', 'slug'];
            public $timestamps = false;
            
            public static function find($id) {
                return new static(['id' => $id, 'name' => 'Test Resource', 'slug' => 'test-slug']);
            }
            
            public function getKey() {
                return $this->attributes['id'] ?? null;
            }
        };

        // Configure a resource type without SoftDeletes
        config()->set('menus.resources.TestResource', [
            'model' => get_class($modelClass),
            'name_field' => 'name',
            'slug_field' => 'slug', 
            'route_pattern' => '/test/{slug}',
        ]);

        $result = $this->service->getResource('TestResource', 1);

        expect($result)->toBeArray();
        expect($result['id'])->toBe(1);
        expect($result['name'])->toBe('Test Resource');
        expect($result['slug'])->toBe('test-slug');
        expect($result['is_deleted'])->toBeFalse();
    });

    test('can handle models with SoftDeletes trait', function () {
        // Mock a model class that uses SoftDeletes
        $modelClass = new class extends \Illuminate\Database\Eloquent\Model {
            use \Illuminate\Database\Eloquent\SoftDeletes;
            
            protected $table = 'soft_delete_resources';
            protected $fillable = ['name', 'slug'];
            public $timestamps = false;
            
            public static function withTrashed() {
                return new static();
            }
            
            public static function find($id) {
                $instance = new static(['id' => $id, 'name' => 'Soft Delete Resource', 'slug' => 'soft-slug']);
                $instance->deleted_at = null; // Not deleted
                return $instance;
            }
            
            public function getKey() {
                return $this->attributes['id'] ?? null;
            }
            
            public function trashed() {
                return !is_null($this->deleted_at);
            }
        };

        // Configure a resource type with SoftDeletes
        config()->set('menus.resources.SoftResource', [
            'model' => get_class($modelClass),
            'name_field' => 'name',
            'slug_field' => 'slug',
            'route_pattern' => '/soft/{slug}',
        ]);

        $result = $this->service->getResource('SoftResource', 1);

        expect($result)->toBeArray();
        expect($result['id'])->toBe(1);
        expect($result['name'])->toBe('Soft Delete Resource');
        expect($result['slug'])->toBe('soft-slug');
        expect($result['is_deleted'])->toBeFalse();
    });

    test('searchResources works without SoftDeletes trait', function () {
        // Mock a collection result
        $modelClass = new class extends \Illuminate\Database\Eloquent\Model {
            protected $table = 'search_resources';
            protected $fillable = ['name', 'slug'];
            public $timestamps = false;
            
            public static function query() {
                return new class {
                    public function where($field, $operator, $value) {
                        return $this;
                    }
                    
                    public function limit($limit) {
                        return $this;
                    }
                    
                    public function get($fields = ['*']) {
                        return collect([
                            (object)['id' => 1, 'name' => 'Search Result 1', 'slug' => 'result-1'],
                            (object)['id' => 2, 'name' => 'Search Result 2', 'slug' => 'result-2'],
                        ]);
                    }
                };
            }
            
            public function getKey() {
                return $this->attributes['id'] ?? null;
            }
            
            public function getKeyName() {
                return 'id';
            }
        };

        config()->set('menus.resources.SearchResource', [
            'model' => get_class($modelClass),
            'name_field' => 'name',
            'slug_field' => 'slug',
            'route_pattern' => '/search/{slug}',
        ]);

        $results = $this->service->searchResources('SearchResource', 'search', 10);

        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($results)->toHaveCount(2);
        expect($results->first()['name'])->toBe('Search Result 1');
        expect($results->first()['slug'])->toBe('result-1');
    });

    test('generates URLs correctly for resource types', function () {
        config()->set('menus.resources.URLResource', [
            'model' => 'App\\Models\\TestModel',
            'name_field' => 'name',
            'slug_field' => 'slug',
            'route_pattern' => '/resources/{slug}',
        ]);

        $url = $this->service->generateUrl('URLResource', 'test-slug-123');

        expect($url)->toBe('/resources/test-slug-123');
    });
});