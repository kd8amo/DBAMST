<?php

use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\TestSystemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here are prefixed with /api and protected by Sanctum auth
| unless explicitly marked as public. Route model binding is active —
| {device}, {site}, {testSystem} etc. resolve automatically via Eloquent.
|
*/

// Health check — unauthenticated, used by monitoring/load balancers.
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// All routes below require a valid Sanctum token.
Route::middleware('auth:sanctum')->group(function () {

    // -------------------------------------------------------------------------
    // Sites (UC-36)
    // -------------------------------------------------------------------------
    Route::prefix('sites')->group(function () {
        Route::get('/',         [SiteController::class, 'index']);
        Route::post('/',        [SiteController::class, 'store']);
        Route::get('/{site}',   [SiteController::class, 'show']);
        Route::patch('/{site}', [SiteController::class, 'update']);
    });

    // -------------------------------------------------------------------------
    // Devices (UC-1 through UC-12)
    // -------------------------------------------------------------------------
    Route::prefix('devices')->group(function () {
        Route::get('/',                          [DeviceController::class, 'index']);
        Route::post('/',                         [DeviceController::class, 'store']);
        Route::get('/find-by-asset-tag',         [DeviceController::class, 'findByAssetTag']);
        Route::get('/{device}',                  [DeviceController::class, 'show']);
        Route::patch('/{device}',                [DeviceController::class, 'update']);
        Route::post('/{device}/retire',          [DeviceController::class, 'retire']);
        Route::post('/{device}/transfer',        [DeviceController::class, 'transfer']);
    });

    // -------------------------------------------------------------------------
    // Test Systems (UC-5 through UC-9)
    // -------------------------------------------------------------------------
    Route::prefix('test-systems')->group(function () {
        Route::get('/',                                          [TestSystemController::class, 'index']);
        Route::post('/',                                         [TestSystemController::class, 'store']);
        Route::get('/{testSystem}',                              [TestSystemController::class, 'show']);
        Route::patch('/{testSystem}',                            [TestSystemController::class, 'update']);
        Route::post('/{testSystem}/assign-device',               [TestSystemController::class, 'assignDevice']);
        Route::delete('/{testSystem}/devices/{device}',          [TestSystemController::class, 'unassignDevice']);
    });

});
