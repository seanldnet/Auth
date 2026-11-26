<?php
use app\middleware\AuthMiddleware;
use app\middleware\AdminMiddleware;
use think\facade\Route;

Route::get('api/health', 'Health/index');
Route::any('api/payments/callback/:channel', 'PaymentCallback/index');
Route::get('api/payments/cashier/:orderNo/:token', 'PaymentCashier/index');
Route::get('api/payments/cashier/:orderNo/:token/status', 'PaymentCashier/status');

Route::post('api/auth/login', 'Auth/login');
Route::post('api/auth/logout', 'Auth/logout')->middleware(AuthMiddleware::class);
Route::get('api/auth/me', 'Auth/me')->middleware(AuthMiddleware::class);
Route::get('api/payments/channels', 'PaymentOrder/channels')->middleware(AuthMiddleware::class);
Route::get('api/payments/orders', 'PaymentOrder/index')->middleware(AuthMiddleware::class);
Route::post('api/payments/orders', 'PaymentOrder/create')->middleware(AuthMiddleware::class);
Route::post('api/auth/change-password', 'Auth/changePassword')->middleware(AuthMiddleware::class);
Route::get('api/admin/payment-channels', 'PaymentChannelAdmin/index')->middleware([AuthMiddleware::class, AdminMiddleware::class]);
Route::put('api/admin/payment-channels/:channelCode', 'PaymentChannelAdmin/save')->middleware([AuthMiddleware::class, AdminMiddleware::class]);
