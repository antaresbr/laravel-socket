<?php

return [
    'date_format' => env('SOCKET_DATE_FORMAT', 'Y-m-d H:i:s.v e'),

    'route' => [
        'prefix' => [
            'api' => env('SOCKET_ROUTE_PREFIX', 'api/socket'),
        ],
    ],
    'auth' => [
        'api' => config('auth.defaults.guard'),
    ],
    'data' => storage_path('app' . DIRECTORY_SEPARATOR . 'socket'),
    'randomId' => 32,
];
