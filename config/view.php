<?php

return [
    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Blade templates are loaded from the resources/views directory. Compiled
    | templates are kept outside the source tree so deployments can rebuild
    | them safely.
    |
    */
    'paths' => [
        resource_path('views'),
    ],

    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),
];