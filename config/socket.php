<?php

return [
    'route' => [
        'prefix' => [
            'api' => env('SOCKET_ROUTE_PREFIX', 'api/socket'),
        ],
    ],
    'auth' => [
        'api' => config('auth.defaults.guard'),
    ],
    'data' => storage_path('app' . DIRECTORY_SEPARATOR . 'socket'),
];
