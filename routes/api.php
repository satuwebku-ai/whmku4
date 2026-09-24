<?php

use App\Http\Controllers\Api\Domain\AvailabilityController;
use App\Http\Controllers\Payment\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('domains/availability', AvailabilityController::class)
        ->middleware('throttle:30,1')
        ->name('domains.availability');

    Route::post('webhooks/payment/{driver}', [WebhookController::class, 'handle'])
        ->whereIn('driver', ['midtrans', 'xendit', 'duitku'])
        ->middleware('throttle:120,1')
        ->name('webhooks.payment');
});