<?php
declare(strict_types=1);

return [
    'default' => env('database.default', 'mysql'),
    'time_query_rule' => [],
    'auto_timestamp' => true,
    'datetime_format' => 'Y-m-d H:i:s',
    'connections' => [
        'mysql' => [
            'type' => env('database.type', 'mysql'),
            'hostname' => env('database.hostname', '127.0.0.1'),
            'database' => env('database.database', 'auth'),
            'username' => env('database.username', 'root'),
            'password' => env('database.password', ''),
            'hostport' => env('database.hostport', '3306'),
            'charset' => env('database.charset', 'utf8mb4'),
            'prefix' => env('database.prefix', 'auth_'),
            'break_reconnect' => true,
            'trigger_sql' => (bool) env('app_debug', false),
        ],
    ],
];