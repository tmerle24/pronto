<?php

use App\Http\Controllers\Guest\MenuController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MenuController::class, 'show'])->name('guest.menu');
Route::get('/e/{event}', [MenuController::class, 'show'])->name('guest.event');
