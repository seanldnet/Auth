<?php
declare(strict_types=1);

return [
    'app_name' => 'Auth API',
    'app_debug' => (bool) env('app_debug', false),
    'app_trace' => (bool) env('app_trace', false),
    'default_timezone' => env('app.default_timezone', 'Asia/Shanghai'),
    'default_lang' => 'zh-cn',
];