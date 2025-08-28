<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Skylark\Menus\Services\ResourceLinkService;

// Use refresh database specifically for this test file
uses(RefreshDatabase::class);

// Create a mock model for testing
class TestPage extends Model
{
    protected $table = 'test_pages';

    protected $fillable = ['title', 'slug', 'content', 'deleted_at'];

    protected $dates = ['deleted_at'];

    public function trashed()
    {
        return ! is_null($this->deleted_at);
    }

    public function getDeletedAtColumn()
    {
        return 'deleted_at';
    }

    public static function withTrashed()
    {
        return static::query();
    }
}

beforeEach(function () {
    // Create test table
    Schema::create('test_pages', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('slug');
        $table->text('content')->nullable();
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();
    });

    // Set up resource configuration
    Config::set('menus.resources', [
        'App\\Models\\Page' => [
            'model' => TestPage::class,
            'name_field' => 'title',
            'slug_field' => 'slug',
            'route_pattern' => '/pages/{slug}',
        ],
        'App\\Models\\Post' => [
            'model' => TestPage::class,
            'name_field' => 'title',
            'slug_field' => 'slug',
            'route_pattern' => '/blog/{slug}',
        ],
    ]);

    $this->service = new ResourceLinkService;
});

afterEach(function () {
    Schema::dropIfExists('test_pages');
});

test('getResourceTypes returns all configured resource types', function () {
    $types = $this->service->getResourceTypes();

    expect($types)->toBe([
        'App\\Models\\Page',
        'App\\Models\\Post',
    ]);
});

test('getResourceConfiguration returns full configuration', function () {
    $config = $this->service->getResourceConfiguration();

    expect($config)->toHaveKey('App\\Models\\Page');
    expect($config)->toHaveKey('App\\Models\\Post');
    expect($config['App\\Models\\Page']['model'])->toBe(TestPage::class);
    expect($config['App\\Models\\Page']['name_field'])->toBe('title');
    expect($config['App\\Models\\Page']['slug_field'])->toBe('slug');
    expect($config['App\\Models\\Page']['route_pattern'])->toBe('/pages/{slug}');
});

test('getResourceConfiguration returns empty array when no resources configured', function () {
    Config::set('menus.resources', []);

    $config = $this->service->getResourceConfiguration();

    expect($config)->toBe([]);
});

test('getResourceConfig returns specific resource configuration', function () {
    $config = $this->service->getResourceConfig('App\\Models\\Page');

    expect($config)->toBe([
        'model' => TestPage::class,
        'name_field' => 'title',
        'slug_field' => 'slug',
        'route_pattern' => '/pages/{slug}',
    ]);
});

test('getResourceConfig throws exception for unconfigured resource type', function () {
    expect(fn () => $this->service->getResourceConfig('App\\Models\\NonExistent'))
        ->toThrow(InvalidArgumentException::class, "Resource type 'App\\Models\\NonExistent' is not configured.");
});

test('getResourceConfig throws exception for invalid configuration missing model', function () {
    Config::set('menus.resources.App\\Models\\Invalid', [
        'name_field' => 'title',
        'slug_field' => 'slug',
        'route_pattern' => '/test/{slug}',
    ]);

    expect(fn () => $this->service->getResourceConfig('App\\Models\\Invalid'))
        ->toThrow(InvalidArgumentException::class);
});

test('getResourceConfig throws exception for invalid configuration missing name_field', function () {
    Config::set('menus.resources.App\\Models\\Invalid', [
        'model' => TestPage::class,
        'slug_field' => 'slug',
        'route_pattern' => '/test/{slug}',
    ]);

    expect(fn () => $this->service->getResourceConfig('App\\Models\\Invalid'))
        ->toThrow(InvalidArgumentException::class);
});

test('getResourceConfig throws exception for invalid configuration missing slug_field', function () {
    Config::set('menus.resources.App\\Models\\Invalid', [
        'model' => TestPage::class,
        'name_field' => 'title',
        'route_pattern' => '/test/{slug}',
    ]);

    expect(fn () => $this->service->getResourceConfig('App\\Models\\Invalid'))
        ->toThrow(InvalidArgumentException::class);
});

test('getResourceConfig throws exception for invalid configuration missing route_pattern', function () {
    Config::set('menus.resources.App\\Models\\Invalid', [
        'model' => TestPage::class,
        'name_field' => 'title',
        'slug_field' => 'slug',
    ]);

    expect(fn () => $this->service->getResourceConfig('App\\Models\\Invalid'))
        ->toThrow(InvalidArgumentException::class);
});

test('getResourceConfig throws exception for non-existent model class', function () {
    Config::set('menus.resources.App\\Models\\Invalid', [
        'model' => 'App\\Models\\NonExistentModel',
        'name_field' => 'title',
        'slug_field' => 'slug',
        'route_pattern' => '/test/{slug}',
    ]);

    expect(fn () => $this->service->getResourceConfig('App\\Models\\Invalid'))
        ->toThrow(InvalidArgumentException::class);
});

test('getResourceConfig throws exception for route pattern without slug placeholder', function () {
    Config::set('menus.resources.App\\Models\\Invalid', [
        'model' => TestPage::class,
        'name_field' => 'title',
        'slug_field' => 'slug',
        'route_pattern' => '/test/invalid',
    ]);

    expect(fn () => $this->service->getResourceConfig('App\\Models\\Invalid'))
        ->toThrow(InvalidArgumentException::class, "Route pattern for 'App\\Models\\Invalid' must contain {slug} placeholder");
});

test('searchResources returns all resources when no search term provided', function () {
    TestPage::create(['title' => 'Home Page', 'slug' => 'home', 'content' => 'Welcome']);
    TestPage::create(['title' => 'About Page', 'slug' => 'about', 'content' => 'About us']);
    TestPage::create(['title' => 'Contact Page', 'slug' => 'contact', 'content' => 'Contact us']);

    $results = $this->service->searchResources('App\\Models\\Page');

    expect($results)->toHaveCount(3);
    expect($results->pluck('name')->toArray())->toContain('Home Page');
    expect($results->pluck('name')->toArray())->toContain('About Page');
    expect($results->pluck('name')->toArray())->toContain('Contact Page');
    expect($results->pluck('slug')->toArray())->toContain('home');
});

test('searchResources filters by search term', function () {
    TestPage::create(['title' => 'Home Page', 'slug' => 'home', 'content' => 'Welcome']);
    TestPage::create(['title' => 'About Page', 'slug' => 'about', 'content' => 'About us']);
    TestPage::create(['title' => 'Contact Page', 'slug' => 'contact', 'content' => 'Contact us']);

    $results = $this->service->searchResources('App\\Models\\Page', 'Home');

    expect($results)->toHaveCount(1);
    expect($results->first()['name'])->toBe('Home Page');
    expect($results->first()['slug'])->toBe('home');
    expect($results->first())->toHaveKey('id');
});

test('searchResources respects limit parameter', function () {
    for ($i = 1; $i <= 10; $i++) {
        TestPage::create([
            'title' => "Page {$i}",
            'slug' => "page-{$i}",
            'content' => "Content {$i}",
        ]);
    }

    $results = $this->service->searchResources('App\\Models\\Page', '', 5);

    expect($results)->toHaveCount(5);
});

test('searchResources excludes soft deleted records', function () {
    TestPage::create(['title' => 'Active Page', 'slug' => 'active', 'content' => 'Active']);
    TestPage::create(['title' => 'Deleted Page', 'slug' => 'deleted', 'content' => 'Deleted', 'deleted_at' => now()]);

    $results = $this->service->searchResources('App\\Models\\Page');

    expect($results)->toHaveCount(1);
    expect($results->first()['name'])->toBe('Active Page');
});

test('searchResources returns collection with correct structure', function () {
    TestPage::create(['title' => 'Test Page', 'slug' => 'test', 'content' => 'Test content']);

    $results = $this->service->searchResources('App\\Models\\Page');

    expect($results)->toHaveCount(1);
    $resource = $results->first();
    expect($resource)->toHaveKey('id');
    expect($resource)->toHaveKey('name');
    expect($resource)->toHaveKey('slug');
    expect($resource['name'])->toBe('Test Page');
    expect($resource['slug'])->toBe('test');
});

test('getResource returns resource data when found', function () {
    $page = TestPage::create(['title' => 'Test Page', 'slug' => 'test', 'content' => 'Test content']);

    $result = $this->service->getResource('App\\Models\\Page', $page->id);

    expect($result)->not->toBeNull();
    expect($result['id'])->toBe($page->id);
    expect($result['name'])->toBe('Test Page');
    expect($result['slug'])->toBe('test');
    expect($result['is_deleted'])->toBeFalse();
});

test('getResource returns null when resource not found', function () {
    $result = $this->service->getResource('App\\Models\\Page', 999);

    expect($result)->toBeNull();
});

test('getResource includes soft deleted resources with is_deleted flag', function () {
    $page = TestPage::create([
        'title' => 'Deleted Page',
        'slug' => 'deleted',
        'content' => 'Deleted content',
        'deleted_at' => now(),
    ]);

    $result = $this->service->getResource('App\\Models\\Page', $page->id);

    expect($result)->not->toBeNull();
    expect($result['name'])->toBe('Deleted Page');
    expect($result['is_deleted'])->toBeTrue();
});

test('generateUrl replaces slug placeholder correctly', function () {
    $url = $this->service->generateUrl('App\\Models\\Page', 'test-page');

    expect($url)->toBe('/pages/test-page');
});

test('generateUrl works with different resource types', function () {
    $pageUrl = $this->service->generateUrl('App\\Models\\Page', 'about-us');
    $postUrl = $this->service->generateUrl('App\\Models\\Post', 'my-first-post');

    expect($pageUrl)->toBe('/pages/about-us');
    expect($postUrl)->toBe('/blog/my-first-post');
});

test('generateUrl throws exception for unconfigured resource type', function () {
    expect(fn () => $this->service->generateUrl('App\\Models\\NonExistent', 'test'))
        ->toThrow(InvalidArgumentException::class);
});

test('validateResourceConfig throws exception for empty required fields', function () {
    Config::set('menus.resources.App\\Models\\Invalid', [
        'model' => '',
        'name_field' => 'title',
        'slug_field' => 'slug',
        'route_pattern' => '/test/{slug}',
    ]);

    expect(fn () => $this->service->getResourceConfig('App\\Models\\Invalid'))
        ->toThrow(InvalidArgumentException::class);
});

test('searchResources throws exception for non-model class', function () {
    Config::set('menus.resources.App\\Models\\Invalid', [
        'model' => 'stdClass',
        'name_field' => 'title',
        'slug_field' => 'slug',
        'route_pattern' => '/test/{slug}',
    ]);

    expect(fn () => $this->service->searchResources('App\\Models\\Invalid'))
        ->toThrow(InvalidArgumentException::class, "Class 'stdClass' must be an instance of Illuminate\\Database\\Eloquent\\Model");
});

test('service handles case insensitive search', function () {
    TestPage::create(['title' => 'Home Page', 'slug' => 'home', 'content' => 'Welcome']);
    TestPage::create(['title' => 'About Page', 'slug' => 'about', 'content' => 'About us']);

    $results = $this->service->searchResources('App\\Models\\Page', 'home');

    expect($results)->toHaveCount(1);
    expect($results->first()['name'])->toBe('Home Page');
});

test('service handles partial search matches', function () {
    TestPage::create(['title' => 'Home Page', 'slug' => 'home', 'content' => 'Welcome']);
    TestPage::create(['title' => 'Homepage Info', 'slug' => 'homepage-info', 'content' => 'Info']);
    TestPage::create(['title' => 'About Page', 'slug' => 'about', 'content' => 'About us']);

    $results = $this->service->searchResources('App\\Models\\Page', 'Home');

    expect($results)->toHaveCount(2);
    expect($results->pluck('name')->toArray())->toContain('Home Page');
    expect($results->pluck('name')->toArray())->toContain('Homepage Info');
});

test('service handles empty search gracefully', function () {
    TestPage::create(['title' => 'Test Page', 'slug' => 'test', 'content' => 'Test']);

    $results = $this->service->searchResources('App\\Models\\Page', '');

    expect($results)->toHaveCount(1);
});

test('service maintains data integrity in search results', function () {
    $page = TestPage::create(['title' => 'Data Test Page', 'slug' => 'data-test', 'content' => 'Testing data integrity']);

    $results = $this->service->searchResources('App\\Models\\Page');

    expect($results)->toHaveCount(1);
    $result = $results->first();
    expect($result['id'])->toBe($page->id);
    expect($result['name'])->toBe($page->title);
    expect($result['slug'])->toBe($page->slug);
});
