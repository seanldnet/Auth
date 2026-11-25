<?php
declare(strict_types=1);

namespace app\\controller;

use think\\response\\Json;

class Health
{
    public function index(): Json
    {
        return json([
            'success' => true,
            'service' => 'auth-api',
            'status' => 'ok',
            'timestamp' => time(),
        ]);
    }
}