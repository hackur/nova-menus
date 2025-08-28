<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Menu Tool Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether the Nova Menu tool is enabled and appears
    | in the Nova dashboard. When disabled, the tool will not be registered
    | and will not affect existing Nova functionality.
    |
    */
    'enabled' => env('NOVA_MENUS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Performance Monitoring
    |--------------------------------------------------------------------------
    |
    | Enable performance monitoring for menu operations. When enabled,
    | menu tool access and operations will be logged for monitoring purposes.
    |
    */
    'monitoring' => [
        'enabled' => env('NOVA_MENUS_MONITORING_ENABLED', true),
        'log_channel' => env('NOVA_MENUS_LOG_CHANNEL', 'single'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resource Configuration
    |--------------------------------------------------------------------------
    |
    | This section defines which Nova resources can be linked to menu items.
    | Each resource type specifies the model class, display field, URL slug
    | field, and frontend route pattern for URL generation.
    |
    */
    'resources' => [
        'Product' => [
            'model' => 'Skylark\NovaCart\Models\Product',
            'name_field' => 'name',
            'slug_field' => 'slug',
            'route_pattern' => '/products/{slug}',
        ],
        'Category' => [
            'model' => 'Skylark\NovaCart\Models\Category',
            'name_field' => 'name',
            'slug_field' => 'slug',
            'route_pattern' => '/categories/{slug}',
        ],
        'Homepage' => [
            'model' => 'Skylark\Content\Models\Homepage',
            'name_field' => 'title',
            'slug_field' => 'slug',
            'route_pattern' => '/{slug}',
        ],
        'Webpage' => [
            'model' => 'Skylark\Content\Models\Webpage',
            'name_field' => 'title',
            'slug_field' => 'slug',
            'route_pattern' => '/pages/{slug}',
        ],
    ],
];
