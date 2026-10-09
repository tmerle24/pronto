<?php

use App\Http\Controllers\Guest\CheckoutController;
use App\Http\Controllers\Guest\MenuController;
use App\Http\Controllers\Guest\OrderController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MenuController::class, 'show'])->name('guest.menu');
Route::get('/e/{event}', [MenuController::class, 'show'])->name('guest.event');
Route::post('/e/{event}/orders', [CheckoutController::class, 'store'])
    ->middleware('throttle:checkout')
    ->name('guest.checkout');

Route::get('/o/{order}', [OrderController::class, 'show'])->name('guest.order');
Route::get('/o/{order}/bezahlen', [OrderController::class, 'pay'])->name('guest.order.pay');
Route::post('/o/{order}/demo-zahlung', [OrderController::class, 'simulatePayment'])->name('guest.order.simulate');

Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');
