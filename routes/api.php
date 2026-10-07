<?php

use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ItemController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('telegram.staff')->group(function () {
    // 1. Availability check
    Route::post('/availability/check', [AvailabilityController::class, 'check'])->name('api.v1.availability.check');

    // 2. Bookings
    Route::post('/bookings', [BookingController::class, 'store'])->name('api.v1.bookings.store');
    Route::get('/bookings/today', [BookingController::class, 'today'])->name('api.v1.bookings.today');

    // 3. Items inventory lookup
    Route::get('/items', [ItemController::class, 'index'])->name('api.v1.items.index');
});
