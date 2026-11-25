<?php
use think\facade\Route;

Route::get('api/health', 'Health/index');
Route::any('api/payments/callback/:channel', 'PaymentCallback/index');
