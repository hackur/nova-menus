<?php

namespace Skylark\Menus\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Skylark\Menus\Models\MenuItem;
use Skylark\Menus\Resources\MenuItemResource;
use Skylark\Menus\Services\ResourceLinkService;

class MenuApiController extends Controller
{
    protected ResourceLinkService $resourceLinkService;

    public function __construct(ResourceLinkService $resourceLinkService)
    {
        $this->resourceLinkService = $resourceLinkService;
    }

    /**
     * Get a single menu by slug with hierarchical structure
     */
    public function getMenu(string $slug): JsonResponse
    {
        $menu = MenuItem::where('slug', $slug)
            ->where('is_root', true)
            ->first();

        if (! $menu) {
            return response()->json([
                'error' => 'Menu not found',
                'message' => "Menu with slug '{$slug}' does not exist",
            ], 404);
        }

        // Get all descendants (without pre-filtering by visibility)
        $allItems = MenuItem::where('_lft', '>', $menu->_lft)
            ->where('_rgt', '<', $menu->_rgt)
            ->orderBy('_lft')
            ->get();

        // Filter hierarchically - children of hidden parents should also be hidden
        $validItems = $this->filterHierarchically($allItems);

        // Build hierarchical structure using nested set toTree method
        $tree = $validItems->toTree();

        return response()->json([
            'slug' => $slug,
            'name' => $menu->name,
            'items' => MenuItemResource::collection($tree),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get multiple menus by comma-separated slugs
     */
    public function getMenus(Request $request): JsonResponse
    {
        $menuSlugs = $request->get('menus', '');

        if (empty($menuSlugs)) {
            return response()->json([
                'error' => 'No menus specified',
                'message' => 'Please provide comma-separated menu slugs via ?menus=slug1,slug2',
            ], 400);
        }

        $slugs = array_map('trim', explode(',', $menuSlugs));
        $menus = MenuItem::whereIn('slug', $slugs)
            ->where('is_root', true)
            ->get()
            ->keyBy('slug');

        $result = [];

        foreach ($slugs as $slug) {
            if (! $menus->has($slug)) {
                $result[$slug] = [
                    'error' => 'Menu not found',
                    'message' => "Menu with slug '{$slug}' does not exist",
                ];

                continue;
            }

            $menu = $menus->get($slug);

            // Get all descendants (without pre-filtering by visibility)
            $allItems = MenuItem::where('_lft', '>', $menu->_lft)
                ->where('_rgt', '<', $menu->_rgt)
                ->orderBy('_lft')
                ->get();

            // Filter hierarchically - children of hidden parents should also be hidden
            $validItems = $this->filterHierarchically($allItems);

            $tree = $validItems->toTree();

            $result[$slug] = [
                'name' => $menu->name,
                'items' => MenuItemResource::collection($tree),
            ];
        }

        return response()->json([
            'menus' => $result,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Filter menu items hierarchically - children of hidden parents are also hidden
     */
    protected function filterHierarchically($items): \Illuminate\Support\Collection
    {
        // Create a set to track which items should be hidden
        $hiddenItems = collect();

        // First pass: identify items that should be hidden based on their own visibility
        foreach ($items as $item) {
            if (! $this->isItemVisible($item)) {
                $hiddenItems->push($item->id);
            }

            // Check resource validity separately with better error handling
            if ($item->resource_type && $item->resource_id) {
                try {
                    if (! $item->hasValidResource()) {
                        $hiddenItems->push($item->id);
                    }
                } catch (\Exception $e) {
                    // Log the error but don't hide the item - fall back to custom_url if available
                    \Log::warning('Resource validation failed in public API', [
                        'item_id' => $item->id,
                        'resource_type' => $item->resource_type,
                        'resource_id' => $item->resource_id,
                        'error' => $e->getMessage(),
                    ]);

                    // Only hide if there's no fallback URL
                    if (! $item->custom_url) {
                        $hiddenItems->push($item->id);
                    }
                }
            }
        }

        // Second pass: hide all descendants of hidden items using nested set logic
        foreach ($items as $item) {
            if ($hiddenItems->contains($item->id)) {
                // This item is hidden, so hide all its descendants
                foreach ($items as $potentialChild) {
                    if ($potentialChild->_lft > $item->_lft &&
                        $potentialChild->_rgt < $item->_rgt) {
                        $hiddenItems->push($potentialChild->id);
                    }
                }
            }
        }

        // Return only items that are not in the hidden set
        return $items->filter(function ($item) use ($hiddenItems) {
            return ! $hiddenItems->contains($item->id);
        });
    }

    /**
     * Check if an item is visible based on temporal constraints
     */
    protected function isItemVisible($item): bool
    {
        $now = now();

        // Check if item is active
        if (! $item->is_active) {
            return false;
        }

        // Check display_at constraint
        if ($item->display_at && $now->lt($item->display_at)) {
            return false;
        }

        // Check hide_at constraint
        if ($item->hide_at && $now->gte($item->hide_at)) {
            return false;
        }

        return true;
    }
}
