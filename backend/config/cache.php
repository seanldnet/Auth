<?php
declare(strict_types=1);

return [
    'default' => env('cache.default', 'redis'),
    'stores' => [
        'redis' => [
            'type' => 'redis',
            'host' => env('redis.host', '127.0.0.1'),
            'port' => (int) env('redis.port', 6379),
            'password' => env('redis.password', ''),
            'select' => (int) env('redis.select', 0),
            'timeout' => 0,
            'expire' => 0,
        ],
        'file' => [
            'type' => 'File',
            'path' => app()->getRuntimePath() . 'cache',
            'prefix' => '',
            'expire' => 0,
        ],
    ],
];