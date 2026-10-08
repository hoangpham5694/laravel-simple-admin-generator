<?php
return [
    'page_name' => 'Simple admin generator',
    'page_name_short' => 'SAG',
    'prefix' => 'admin',
    'middleware' => ['web'], // you probably want to include 'web' here
    // Absolute path in the host Laravel application. Null uses resources/admin-menu-items.json.
    'menu_json_path' => null,
];
