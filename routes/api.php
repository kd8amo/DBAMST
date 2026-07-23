<?php

use App\Http\Controllers\Api\ApiKeyController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\FaultReportController;
use App\Http\Controllers\Api\MaintenanceController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\TestSystemController;
use App\Http\Controllers\Api\UserController;
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

// Authentication — unauthenticated.
Route::post('/login',  [UserController::class, 'login']);

// All routes below require a valid Sanctum token.
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [UserController::class, 'logout']);

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
        Route::get('/',                      [DeviceController::class, 'index']);
        Route::post('/',                     [DeviceController::class, 'store']);
        Route::get('/find-by-asset-tag',     [DeviceController::class, 'findByAssetTag']);
        Route::get('/{device}',              [DeviceController::class, 'show']);
        Route::patch('/{device}',            [DeviceController::class, 'update']);
        Route::post('/{device}/retire',      [DeviceController::class, 'retire']);
        Route::post('/{device}/transfer',    [DeviceController::class, 'transfer']);
        Route::post('/{device}/out-for-cal', [MaintenanceController::class, 'markOutForCalibration']);
    });

    // -------------------------------------------------------------------------
    // Test Systems (UC-5 through UC-9)
    // -------------------------------------------------------------------------
    Route::prefix('test-systems')->group(function () {
        Route::get('/',                                 [TestSystemController::class, 'index']);
        Route::post('/',                                [TestSystemController::class, 'store']);
        Route::get('/{testSystem}',                     [TestSystemController::class, 'show']);
        Route::patch('/{testSystem}',                   [TestSystemController::class, 'update']);
        Route::post('/{testSystem}/assign-device',      [TestSystemController::class, 'assignDevice']);
        Route::delete('/{testSystem}/devices/{device}', [TestSystemController::class, 'unassignDevice']);
    });

    // -------------------------------------------------------------------------
    // Bookings (UC-21 through UC-25)
    // -------------------------------------------------------------------------
    Route::prefix('bookings')->group(function () {
        Route::get('/',                   [BookingController::class, 'index']);
        Route::post('/',                  [BookingController::class, 'store']);
        Route::get('/{booking}',          [BookingController::class, 'show']);
        Route::patch('/{booking}',        [BookingController::class, 'update']);
        Route::post('/{booking}/cancel',  [BookingController::class, 'cancel']);
        Route::post('/{booking}/confirm', [BookingController::class, 'confirm']);
    });

    // -------------------------------------------------------------------------
    // Fault Reports (UC-18 through UC-19)
    // -------------------------------------------------------------------------
    Route::prefix('fault-reports')->group(function () {
        Route::get('/',                [FaultReportController::class, 'index']);
        Route::post('/',               [FaultReportController::class, 'store']);
        Route::get('/{faultReport}',   [FaultReportController::class, 'show']);
        Route::patch('/{faultReport}', [FaultReportController::class, 'update']);
    });

    // -------------------------------------------------------------------------
    // Maintenance — Schedules (UC-13) and Events (UC-14 through UC-17)
    // -------------------------------------------------------------------------
    Route::prefix('maintenance')->group(function () {
        Route::get('/due',                    [MaintenanceController::class, 'dueList']);
        Route::get('/schedules',              [MaintenanceController::class, 'indexSchedules']);
        Route::post('/schedules',             [MaintenanceController::class, 'storeSchedule']);
        Route::patch('/schedules/{schedule}', [MaintenanceController::class, 'updateSchedule']);
        Route::get('/events',                 [MaintenanceController::class, 'indexEvents']);
        Route::post('/events',                [MaintenanceController::class, 'storeEvent']);
    });

    // -------------------------------------------------------------------------
    // Users (UC-32)
    // -------------------------------------------------------------------------
    Route::prefix('users')->group(function () {
        Route::get('/',                  [UserController::class, 'index']);
        Route::post('/',                 [UserController::class, 'store']);
        Route::get('/profile',           [UserController::class, 'show']);
        Route::patch('/profile',         [UserController::class, 'updateProfile']);
        Route::get('/{user}',            [UserController::class, 'show']);
        Route::patch('/{user}',          [UserController::class, 'update']);
        Route::post('/{user}/deactivate',[UserController::class, 'deactivate']);
    });

    // -------------------------------------------------------------------------
    // API Keys (UC-33)
    // -------------------------------------------------------------------------
    Route::prefix('api-keys')->group(function () {
        Route::get('/',              [ApiKeyController::class, 'index']);
        Route::post('/',             [ApiKeyController::class, 'store']);
        Route::post('/{apiKey}/revoke', [ApiKeyController::class, 'revoke']);
    });

    // -------------------------------------------------------------------------
    // Audit Log (UC-34)
    // -------------------------------------------------------------------------
    Route::prefix('audit-log')->group(function () {
        Route::get('/',                            [AuditLogController::class, 'index']);
        Route::get('/{entityType}/{entityId}',     [AuditLogController::class, 'forEntity']);
    });

});
