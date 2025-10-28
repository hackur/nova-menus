<?php

namespace Skylark\Menus\Http\Controllers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Skylark\Menus\Models\MenuItem;
use Skylark\Menus\Services\ResourceLinkService;

class MenuController
{
    /**
     * Display a listing of menus (root nodes).
     */
    public function index(): JsonResponse
    {
        try {
            // Check if menu_items table exists
            if (! DB::getSchemaBuilder()->hasTable('menu_items')) {
                return response()->json([
                    'data' => [],
                    'message' => 'Menu system not installed. Please run: php artisan vendor:publish --tag="menus-migrations" && php artisan migrate',
                ]);
            }

            $menus = MenuItem::roots()
                ->select(['id', 'name', 'slug', 'max_depth', 'is_active', 'created_at', 'updated_at', '_lft', '_rgt'])
                ->get()
                ->map(function ($menu) {
                    // Count all child items (non-root items in this menu's tree)
                    $itemsCount = MenuItem::whereBetween('_lft', [$menu->_lft + 1, $menu->_rgt - 1])
                        ->count();

                    $menuArray = $menu->toArray();
                    // Remove nested set columns from output to keep API clean
                    unset($menuArray['_lft'], $menuArray['_rgt']);

                    return array_merge($menuArray, [
                        'items_count' => $itemsCount,
                    ]);
                });

            return response()->json([
                'success' => true,
                'data' => $menus,
                'message' => 'Menus retrieved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve menus',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created menu (root node).
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Check if menu_items table exists
            if (! DB::getSchemaBuilder()->hasTable('menu_items')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Menu system not installed. Please run: php artisan vendor:publish --tag="menus-migrations" && php artisan migrate',
                ], 500);
            }

            $validated = $request->validate([
                'name' => 'required|string|min:3|max:255',
                'slug' => 'nullable|string|max:255|unique:menu_items,slug',
                'is_active' => 'boolean',
                'max_depth' => 'integer|min:1|max:10',
            ]);

            // Generate slug if not provided
            if (empty($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            $menu = MenuItem::createMenu($validated);

            return response()->json([
                'success' => true,
                'data' => $menu,
                'message' => 'Menu created successfully',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create menu',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified menu (root node).
     */
    public function show(int $id): JsonResponse
    {
        try {
            $menu = MenuItem::where('id', $id)->where('is_root', true)->withDepth()->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => $menu,
                'message' => 'Menu retrieved successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve menu',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified menu (root node).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $menu = MenuItem::where('id', $id)->where('is_root', true)->firstOrFail();

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'slug' => 'sometimes|nullable|string|max:255|unique:menu_items,slug,'.$id,
                'is_active' => 'sometimes|boolean',
                'max_depth' => 'sometimes|integer|min:1|max:10',
            ]);

            $menu->update($validated);

            return response()->json([
                'success' => true,
                'data' => $menu->fresh(),
                'message' => 'Menu updated successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update menu',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified menu (root node and all descendants).
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $menu = MenuItem::where('id', $id)->where('is_root', true)->firstOrFail();

            // Delete the entire tree (root and all descendants)
            $menu->delete();

            return response()->json([
                'success' => true,
                'message' => 'Menu deleted successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete menu',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get menu items for a specific menu as a hierarchical tree structure.
     */
    public function items(int $id): JsonResponse
    {
        try {
            $rootMenu = MenuItem::where('id', $id)->where('is_root', true)->firstOrFail();

            // Get descendants of the root node as a tree structure
            $menuItems = $rootMenu->descendants()
                ->withDepth()     // Calculate depth dynamically from nested set
                ->defaultOrder()  // Use nested set's default ordering
                ->get()
                ->map(function ($item) {
                    // Add resource name for admin interface
                    if ($item->resource_type && $item->resource_id) {
                        $resourceData = $item->getResourceData();
                        $item->resource_name = $resourceData['name'] ?? null;
                    }

                    return $item;
                })
                ->toTree();       // Convert to hierarchical tree structure

            return response()->json([
                'success' => true,
                'data' => $menuItems,
                'message' => 'Menu items retrieved successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve menu items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rebuild menu structure using rebuildFromArray with $delete=true
     */
    public function rebuild(Request $request, int $id): JsonResponse
    {
        try {
            $rootMenu = MenuItem::where('id', $id)->where('is_root', true)->firstOrFail();

            $validated = $request->validate([
                'menu_structure' => 'required|array',
                'menu_structure.*.id' => 'sometimes|integer|exists:menu_items,id',
                'menu_structure.*.name' => 'required|string|max:255',
                'menu_structure.*.custom_url' => 'nullable|string|max:500',
                'menu_structure.*.resource_type' => 'nullable|string|max:100',
                'menu_structure.*.resource_id' => 'nullable|integer',
                'menu_structure.*.resource_slug' => 'nullable|string|max:255',
                'menu_structure.*.display_at' => 'nullable|date',
                'menu_structure.*.hide_at' => 'nullable|date',
                'menu_structure.*.is_active' => 'boolean',
                'menu_structure.*.children' => 'sometimes|array',
            ]);

            $menuStructure = $validated['menu_structure'];

            // Use Laravel Nestedset's rebuildSubtree to rebuild only this menu's items
            // This will constrain the rebuild to the descendants of this root menu
            MenuItem::rebuildSubtree($rootMenu, $menuStructure);

            // Reload the menu items to return fresh data
            $updatedMenuItems = $rootMenu->descendants()
                ->withDepth()
                ->defaultOrder()
                ->get()
                ->toTree();

            return response()->json([
                'success' => true,
                'data' => $updatedMenuItems,
                'message' => 'Menu structure rebuilt successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Menu not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rebuild menu structure',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reorder menu items using nested set model for drag-and-drop functionality.
     */
    public function reorder(Request $request, int $id): JsonResponse
    {
        try {
            // Verify menu exists (get root node)
            $menu = MenuItem::where('id', $id)
                ->where('is_root', true)
                ->firstOrFail();

            $validated = $request->validate([
                'items' => 'required|array',
                'items.*.id' => 'required|exists:menu_items,id',
                'items.*.position' => 'required|integer|min:0',
                'items.*.parent_id' => 'nullable|exists:menu_items,id',
            ]);

            DB::transaction(function () use ($validated, $id, $menu) {
                foreach ($validated['items'] as $itemData) {
                    $menuItem = MenuItem::findOrFail($itemData['id']);

                    // Verify the menu item belongs to the correct menu root
                    $itemMenuRoot = $menuItem->getMenuRoot();
                    if (! $itemMenuRoot || $itemMenuRoot->id !== $id) {
                        throw new \InvalidArgumentException("Menu item {$itemData['id']} does not belong to menu {$id}");
                    }

                    // Check max depth validation if parent is changing
                    if ($itemData['parent_id'] !== $menuItem->parent_id) {
                        $parentDepth = 0;
                        if ($itemData['parent_id']) {
                            $parentWithDepth = MenuItem::withDepth()
                                ->where('id', $itemData['parent_id'])
                                ->first();
                            $parentDepth = $parentWithDepth ? $parentWithDepth->depth + 1 : 0;
                        }

                        $maxDepth = $menu->max_depth ?? 6; // Default max depth if not set
                        if ($parentDepth >= $maxDepth) {
                            throw new \InvalidArgumentException("Moving item would exceed maximum depth limit of {$maxDepth}");
                        }
                    }

                    // Update parent relationship first (for nested set operations)
                    if ($itemData['parent_id'] !== $menuItem->parent_id) {
                        if ($itemData['parent_id']) {
                            $parentItem = MenuItem::findOrFail($itemData['parent_id']);
                            $menuItem->appendToNode($parentItem)->save();
                        } else {
                            // Move to root level under menu
                            $menuItem->appendToNode($menu)->save();
                        }
                    }

                    // Update position within the same level
                    $menuItem->update([
                        'position' => $itemData['position'],
                    ]);
                }

                // Rebuild nested set values to ensure consistency
                MenuItem::rebuildTree($menu->descendants()->get()->toArray());
            });

            return response()->json([
                'success' => true,
                'message' => 'Menu items reordered successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Menu or menu item not found',
            ], 404);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder menu items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created menu item.
     */
    public function storeItem(Request $request): JsonResponse
    {
        try {
            $validationRules = MenuItem::validationRules();
            $validationRules['menu_id'] = 'required|integer|exists:menu_items,id'; // Root menu validation
            $validationRules['parent_id'] = 'nullable|integer|exists:menu_items,id';
            $validationRules['icon'] = 'nullable|string|max:100';
            $validationRules['target'] = 'nullable|in:_self,_blank';
            $validationRules['css_class'] = 'nullable|string|max:255';
            $validationRules['position'] = 'integer|min:0';
            $validationRules['is_active'] = 'boolean';

            $validated = $request->validate($validationRules);

            // Find the root menu and verify it's actually a root
            $rootMenu = MenuItem::where('id', $validated['menu_id'])
                ->where('is_root', true)
                ->firstOrFail();

            // If no parent_id is specified, attach to the root menu
            if (empty($validated['parent_id'])) {
                $validated['parent_id'] = $rootMenu->id;
            }

            // Set default position if not provided
            if (! isset($validated['position'])) {
                $lastItem = MenuItem::where('parent_id', $validated['parent_id'])
                    ->max('position');
                $validated['position'] = ($lastItem ?? -1) + 1;
            }

            // Remove menu_id from validated data since it's not a database field
            unset($validated['menu_id']);

            // Create the menu item
            $menuItem = MenuItem::create($validated);

            // Use nested set operations to properly attach to parent
            if ($validated['parent_id']) {
                $parent = MenuItem::findOrFail($validated['parent_id']);
                $menuItem->appendToNode($parent)->save();
            }

            return response()->json([
                'success' => true,
                'data' => $menuItem->load('children'),
                'message' => 'Menu item created successfully',
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Root menu not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create menu item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified menu item.
     */
    public function updateItem(Request $request, int $id): JsonResponse
    {
        try {
            $menuItem = MenuItem::findOrFail($id);

            $validationRules = MenuItem::updateValidationRules($id);
            // Make all fields optional for updates by adding 'sometimes' rule
            $validationRules = array_map(function ($rule) {
                // Properly remove 'required|' from the beginning of the rule
                if (str_starts_with($rule, 'required|')) {
                    $rule = substr($rule, 9); // Remove 'required|' (9 characters)
                }

                return 'sometimes|'.$rule;
            }, $validationRules);
            $validationRules['icon'] = 'sometimes|nullable|string|max:100';
            $validationRules['target'] = 'sometimes|nullable|in:_self,_blank';
            $validationRules['css_class'] = 'sometimes|nullable|string|max:255';
            $validationRules['position'] = 'sometimes|integer|min:0';
            $validationRules['is_active'] = 'sometimes|boolean';

            $validated = $request->validate($validationRules);

            $menuItem->update($validated);

            return response()->json([
                'success' => true,
                'data' => $menuItem->fresh(['children']),
                'message' => 'Menu item updated successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update menu item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified menu item.
     */
    public function destroyItem(int $id): JsonResponse
    {
        try {
            $menuItem = MenuItem::findOrFail($id);

            // Delete all child items recursively
            $this->deleteMenuItemRecursively($menuItem);

            return response()->json([
                'success' => true,
                'message' => 'Menu item deleted successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Menu item not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete menu item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available resource types for menu item linking.
     */
    public function resourceTypes(): JsonResponse
    {
        try {
            $resourceService = app(ResourceLinkService::class);
            $resourceTypes = $resourceService->getResourceTypes();

            // Format for dropdown (value => label)
            $formattedTypes = collect($resourceTypes)->mapWithKeys(function ($type) {
                return [$type => $type];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedTypes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load resource types',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search resources of a specific type for menu item linking.
     */
    public function searchResources(Request $request, string $resourceType): JsonResponse
    {
        try {
            $validated = $request->validate([
                'q' => 'nullable|string|max:255',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            $resourceService = app(ResourceLinkService::class);
            $resources = $resourceService->searchResources(
                $resourceType,
                $validated['q'] ?? '',
                $validated['limit'] ?? 50
            );

            return response()->json([
                'success' => true,
                'data' => $resources,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search resources',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Recursively delete menu item and its children.
     */
    private function deleteMenuItemRecursively(MenuItem $menuItem): void
    {
        // Delete all children first
        foreach ($menuItem->children as $child) {
            $this->deleteMenuItemRecursively($child);
        }

        // Delete the menu item itself
        $menuItem->delete();
    }
}
