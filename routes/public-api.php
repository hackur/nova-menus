<?php

use Illuminate\Support\Facades\Route;
use Skylark\Menus\Http\Controllers\MenuApiController;

/*
|--------------------------------------------------------------------------
| Public Menu API Routes
|--------------------------------------------------------------------------
|
| These routes provide public API access to menu data for frontend
| applications. They return filtered menu structures with active items only.
|
*/

// Single menu endpoint
Route::get('menus/{slug}', [MenuApiController::class, 'getMenu'])
    ->where('slug', '[a-zA-Z0-9\-_]+');

// Multi-menu endpoint
Route::get('menus', [MenuApiController::class, 'getMenus']);
