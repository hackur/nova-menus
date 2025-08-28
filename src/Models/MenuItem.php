<?php

namespace Skylark\Menus\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Kalnoy\Nestedset\NodeTrait;
use Skylark\Menus\Services\ResourceLinkService;

class MenuItem extends Model
{
    use HasFactory, NodeTrait;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\MenuItemFactory::new();
    }

    protected $fillable = [
        'parent_id',
        'name',
        'custom_url',
        'resource_type',
        'resource_id',
        'resource_slug',
        'display_at',
        'hide_at',
        'icon',
        'target',
        'css_class',
        'position',
        'is_active',
        'is_root',
        'slug',
        'max_depth',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'resource_id' => 'integer',
        'position' => 'integer',
        'is_active' => 'boolean',
        'is_root' => 'boolean',
        'max_depth' => 'integer',
        'display_at' => 'datetime',
        'hide_at' => 'datetime',
    ];

    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id');
    }

    /**
     * Scope to get root nodes (menus)
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->where('is_root', true);
    }

    /**
     * Scope to get items for a specific menu by slug
     */
    public function scopeForMenu(Builder $query, string $slug): Builder
    {
        $root = static::where('slug', $slug)->where('is_root', true)->first();

        if (! $root) {
            return $query->whereNull('id'); // Return empty
        }

        return $query->descendantsOf($root->id);
    }

    /**
     * Get the root node for this menu item
     */
    public function getMenuRoot(): ?MenuItem
    {
        if ($this->is_root) {
            return $this;
        }

        return $this->ancestors()->where('is_root', true)->first();
    }

    /**
     * Create a new menu (root node)
     */
    public static function createMenu(array $attributes): MenuItem
    {
        return static::create([
            'name' => $attributes['name'],
            'slug' => $attributes['slug'] ?? \Str::slug($attributes['name']),
            'max_depth' => $attributes['max_depth'] ?? 2,
            'is_active' => $attributes['is_active'] ?? true,
            'is_root' => true,
            'parent_id' => null,
        ]);
    }

    public function scopeVisible(Builder $query): Builder
    {
        $now = now();

        return $query->where(function ($q) use ($now) {
            $q->whereNull('display_at')
                ->orWhere('display_at', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('hide_at')
                ->orWhere('hide_at', '>', $now);
        });
    }

    /**
     * Scope for active menu items (alias for visible for consistency)
     */
    public function scopeIsActive(Builder $query): Builder
    {
        return $query->visible();
    }

    /**
     * Scope for items visible at a specific timestamp
     */
    public function scopeIsVisibleAt(Builder $query, $timestamp): Builder
    {
        return $query->where(function ($q) use ($timestamp) {
            $q->whereNull('display_at')
                ->orWhere('display_at', '<=', $timestamp);
        })->where(function ($q) use ($timestamp) {
            $q->whereNull('hide_at')
                ->orWhere('hide_at', '>', $timestamp);
        });
    }

    public function isVisible(): bool
    {
        $now = now();

        $displayCheck = is_null($this->display_at) || $this->display_at <= $now;
        $hideCheck = is_null($this->hide_at) || $this->hide_at > $now;

        return $displayCheck && $hideCheck;
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->custom_url) {
            return $this->custom_url;
        }

        // Use resource configuration for URL generation
        if ($this->resource_type && $this->resource_slug) {
            try {
                $resourceService = app(ResourceLinkService::class);

                return $resourceService->generateUrl($this->resource_type, $this->resource_slug);
            } catch (\Exception $e) {
                // Log error and fall back to custom_url if available
                Log::warning('Failed to generate resource URL for menu item', [
                    'menu_item_id' => $this->id,
                    'resource_type' => $this->resource_type,
                    'resource_slug' => $this->resource_slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Legacy fallback for existing resource relationships
        if ($this->resource && method_exists($this->resource, 'getUrl')) {
            return $this->resource->getUrl();
        }

        return null;
    }

    /**
     * Check if the linked resource exists and is not deleted.
     */
    public function hasValidResource(): bool
    {
        if (! $this->resource_type || ! $this->resource_id) {
            return true; // No resource linked, so it's valid in that context
        }

        try {
            $resourceService = app(ResourceLinkService::class);
            $resource = $resourceService->getResource($this->resource_type, $this->resource_id);

            return $resource && ! ($resource['is_deleted'] ?? false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Scope to filter out menu items with soft-deleted resources
     * Note: This includes items without resources (custom URLs) as they are always valid
     */
    public function scopeWithValidResources(Builder $query): Builder
    {
        return $query->where(function ($q) {
            // Include items without resources (custom URLs) - these are always valid
            $q->whereNull('resource_type')
                ->orWhereNull('resource_id');
        });
    }

    /**
     * Filter collection to only include items with valid (non-soft-deleted) resources
     */
    public function filterValidResources($collection)
    {
        return $collection->filter(function ($item) {
            return $item->hasValidResource();
        });
    }

    /**
     * Get the resource data if it exists.
     */
    public function getResourceData(): ?array
    {
        if (! $this->resource_type || ! $this->resource_id) {
            return null;
        }

        try {
            $resourceService = app(ResourceLinkService::class);

            return $resourceService->getResource($this->resource_type, $this->resource_id);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get validation rules for MenuItem model
     */
    public static function validationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'custom_url' => 'nullable|string|max:2048',
            'resource_type' => 'nullable|string|max:255',
            'resource_id' => 'nullable|integer|min:1',
            'resource_slug' => 'nullable|string|max:255',
            'display_at' => 'nullable|date',
            'hide_at' => 'nullable|date|after:display_at',
            'parent_id' => 'nullable|exists:menu_items,id',
        ];
    }

    /**
     * Get validation rules for updating MenuItem model
     */
    public static function updateValidationRules(int $id): array
    {
        return [
            'name' => 'required|string|max:255',
            'custom_url' => 'nullable|string|max:2048',
            'resource_type' => 'nullable|string|max:255',
            'resource_id' => 'nullable|integer|min:1',
            'resource_slug' => 'nullable|string|max:255',
            'display_at' => 'nullable|date',
            'hide_at' => 'nullable|date|after:display_at',
            'parent_id' => "nullable|exists:menu_items,id|not_in:{$id}",
        ];
    }
}
