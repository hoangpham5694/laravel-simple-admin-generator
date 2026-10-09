<?php
return [
    'page_name' => 'Simple admin generator',
    'page_name_short' => 'SAG',
    'prefix' => 'admin',
    'middleware' => ['web'], // you probably want to include 'web' here
    // Absolute path in the host Laravel application. Null uses resources/admin-menu-items.json.
    'menu_json_path' => null,
    'search' => [
        'enabled' => false,
        'providers' => [],
        'min_length' => 2,
        'max_length' => 200,
        'limit_per_provider' => 10,
        'suggestions' => [
            'enabled' => true,
            'debounce_ms' => 300,
            'limit_per_provider' => 3,
            'max_results' => 10,
        ],
    ],
];
