<?php
declare(strict_types=1);

return [
    'default' => 'File',
    'channels' => [
        'File' => [
            'type' => 'File',
            'path' => app()->getRuntimePath() . 'log',
            'level' => ['error', 'sql'],
            'single' => false,
            'apart_level' => [],
            'max_files' => 30,
            'json' => false,
            'realtime_write' => true,
        ],
    ],
];
