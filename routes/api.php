<?php

use App\Http\Controllers\Api\V1\ApiEntitlementController;
use App\Http\Controllers\Api\V1\ApiUsageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['auth.org_api_key', 'throttle:organization-api'])
    ->group(function () {
        Route::post('/usage-events', [ApiUsageController::class, 'store'])
            ->name('api.v1.usage-events.store');

        Route::post('/entitlements/check', [ApiEntitlementController::class, 'check'])
            ->name('api.v1.entitlements.check');
    });
