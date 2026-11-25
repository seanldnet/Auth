<?php
declare(strict_types=1);

return [
    'app_name' => 'Auth API',
    'app_debug' => (bool) env('APP_DEBUG', false),
    'app_trace' => (bool) env('APP_TRACE', false),
    'default_timezone' => env('APP_DEFAULT_TIMEZONE', 'Asia/Shanghai'),
    'default_lang' => 'zh-cn',
];