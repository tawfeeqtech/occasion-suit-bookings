<?php

use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\ReturnController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('telegram.staff')->group(function () {
    // 1. Availability check
    Route::post('/availability/check', [AvailabilityController::class, 'check'])->name('api.v1.availability.check');

    // 2. Bookings
    Route::post('/bookings', [BookingController::class, 'store'])->name('api.v1.bookings.store');
    Route::get('/bookings/today', [BookingController::class, 'today'])->name('api.v1.bookings.today');

    // 3. Items inventory lookup
    Route::get('/items', [ItemController::class, 'index'])->name('api.v1.items.index');

    // 4. Returns & Collateral
    Route::post('/bookings/{id}/return', [ReturnController::class, 'processReturn'])->name('api.v1.bookings.return');
    Route::post('/bookings/{id}/collateral/release', [ReturnController::class, 'releaseCollateral'])->name('api.v1.bookings.collateral.release');
    Route::post('/bookings/{bookingId}/items/{itemId}/waive-penalty', [ReturnController::class, 'waivePenalty'])->name('api.v1.bookings.items.waive');
    Route::post('/bookings/{id}/pay-penalty', [ReturnController::class, 'payPenalty'])->name('api.v1.bookings.pay-penalty');
});
