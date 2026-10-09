<?php

use App\Http\Controllers\Display\BoardController;
use App\Http\Controllers\Guest\CheckoutController;
use App\Http\Controllers\Guest\MenuController;
use App\Http\Controllers\Guest\NotificationController;
use App\Http\Controllers\Guest\OrderController;
use App\Http\Controllers\Team\CounterController;
use App\Http\Controllers\Team\IntakeController;
use App\Http\Controllers\Team\LoginController;
use App\Http\Controllers\Team\QueueController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MenuController::class, 'show'])->name('guest.menu');
Route::get('/e/{event}', [MenuController::class, 'show'])->name('guest.event');
Route::post('/e/{event}/orders', [CheckoutController::class, 'store'])
    ->middleware('throttle:checkout')
    ->name('guest.checkout');

Route::get('/o/{order}', [OrderController::class, 'show'])->name('guest.order');
Route::get('/o/{order}/bezahlen', [OrderController::class, 'pay'])->name('guest.order.pay');
Route::post('/o/{order}/push', [NotificationController::class, 'push'])->middleware('throttle:10,1')->name('guest.order.push');
Route::post('/o/{order}/sms', [NotificationController::class, 'sms'])->middleware('throttle:10,1')->name('guest.order.sms');
Route::post('/o/{order}/demo-zahlung', [OrderController::class, 'simulatePayment'])->name('guest.order.simulate');

Route::get('/display/{token}', [BoardController::class, 'show'])->name('display');
Route::get('/display/{token}/state', [BoardController::class, 'state'])->name('display.state');

Route::prefix('team')->name('team.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
        Route::redirect('/', '/team/queue');
        Route::get('/queue', [QueueController::class, 'show'])->name('queue');
        Route::get('/queue/state', [QueueController::class, 'state'])->name('queue.state');
        Route::post('/orders/{order:id}/advance', [QueueController::class, 'advance'])->name('orders.advance');
        Route::post('/orders/{order:id}/undo', [QueueController::class, 'undo'])->name('orders.undo');

        Route::get('/counter', [CounterController::class, 'show'])->name('counter');
        Route::post('/counter', [CounterController::class, 'store'])->name('counter.store');

        Route::post('/intake/pause', [IntakeController::class, 'pause'])->name('intake.pause');
        Route::post('/intake/accepting', [IntakeController::class, 'accepting'])->name('intake.accepting');
        Route::post('/menu-items/{item}/sold-out', [IntakeController::class, 'soldOut'])->name('menu-items.sold-out');
    });
});

Route::post('/webhooks/stripe', StripeWebhookController::class)->name('webhooks.stripe');
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('webhooks.whatsapp');
