<?php
use think\facade\Route;

Route::get('api/health', 'Health/index');
Route::any('api/payments/callback/:channel', 'PaymentCallback/index');
Route::get('api/payments/cashier/:orderNo/:token', 'PaymentCashier/index');
Route::get('api/payments/cashier/:orderNo/:token/status', 'PaymentCashier/status');
