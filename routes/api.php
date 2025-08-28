<?php

use Illuminate\Support\Facades\Route;
use Skylark\Menus\Http\Controllers\MenuController;

/*
|--------------------------------------------------------------------------
| Tool API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your tool. These routes
| are loaded by the ServiceProvider of your tool. They are protected
| by your tool's "Authorize" middleware by default. Now, go build!
|
*/

// Menu CRUD routes
Route::apiResource('menus', MenuController::class);

// Get menu items for a specific menu
Route::get('menus/{id}/items', [MenuController::class, 'items']);

// Rebuild menu structure using Laravel Nestedset
Route::put('menus/{id}/items/rebuild', [MenuController::class, 'rebuild']);

// Menu item reordering for drag-and-drop
Route::put('menus/{id}/items/reorder', [MenuController::class, 'reorder']);

// Menu Item CRUD routes - using single controller
Route::post('menu-items', [MenuController::class, 'storeItem']);
Route::put('menu-items/{id}', [MenuController::class, 'updateItem']);
Route::delete('menu-items/{id}', [MenuController::class, 'destroyItem']);

// Resource selection API endpoints
Route::get('resource-types', [MenuController::class, 'resourceTypes']);
Route::get('resources/{resource_type}/search', [MenuController::class, 'searchResources']);
