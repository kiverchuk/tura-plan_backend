<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\DataExchangeController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PublicShareController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\ResourceController;
use App\Http\Controllers\Api\V1\RotationController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ShareController;
use App\Http\Controllers\Api\V1\SupportController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\VerificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:auth');
        Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth');
        Route::post('password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:auth');
        Route::get('verify-email/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
        Route::post('verify-email', [VerificationController::class, 'resend'])->middleware(['auth:sanctum', 'throttle:auth']);
        Route::middleware('auth:sanctum')->post('logout', [AuthController::class, 'logout']);
    });

    Route::get('health/live', [HealthController::class, 'live']);
    Route::get('health/ready', [HealthController::class, 'ready']);
    Route::get('app-config', [HealthController::class, 'config']);
    Route::get('feature-flags', [HealthController::class, 'flags']);
    Route::get('holiday-packs', [HolidayController::class, 'index']);
    Route::get('holiday-packs/{id}', [HolidayController::class, 'show']);
    Route::get('shared/{token}', [PublicShareController::class, 'show'])->middleware('throttle:shares');
    Route::post('billing/apple/notifications', [BillingController::class, 'appleNotification']);
    Route::post('billing/google/notifications', [BillingController::class, 'googleNotification']);
    Route::get('privacy/requests/{id}', [PrivacyController::class, 'publicStatus'])->middleware('throttle:shares');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [ProfileController::class, 'show']);
        Route::patch('me', [ProfileController::class, 'update']);
        Route::get('me/devices', [DeviceController::class, 'index']);
        Route::delete('me/devices/{id}', [DeviceController::class, 'destroy']);
        foreach (['employers', 'calendars', 'shift-types', 'shifts', 'personal-events', 'rotation-templates'] as $resource) {
            Route::match(['GET', 'POST'], "/{$resource}", fn (Request $request) => app(ResourceController::class)->dispatch($request, $resource))->middleware('premium');
            Route::match(['GET', 'PATCH', 'DELETE'], "/{$resource}/{id}", fn (Request $request, string $id) => app(ResourceController::class)->dispatch($request, $resource, $id))->middleware('premium');
        }
        Route::post('rotations/preview', [RotationController::class, 'preview'])->middleware('premium');
        Route::post('rotations/apply', [RotationController::class, 'apply'])->middleware(['premium', 'idempotency']);
        Route::post('rotations/undo', [RotationController::class, 'undo'])->middleware('premium');
        Route::post('sync/push', [SyncController::class, 'push'])->middleware(['premium', 'idempotency']);
        Route::get('sync/pull', [SyncController::class, 'pull'])->middleware('premium');
        Route::post('sync/conflicts/{id}/resolve', [SyncController::class, 'resolve'])->middleware('premium');
        Route::get('search', SearchController::class)->middleware(['premium', 'throttle:search']);
        foreach (['hours', 'earnings', 'overtime'] as $report) {
            Route::get("reports/{$report}", [ReportController::class, $report])->middleware('premium');
        }
        Route::apiResource('shares', ShareController::class)->only(['index', 'store', 'show', 'update', 'destroy'])->middleware('premium');
        Route::post('exports', [DataExchangeController::class, 'export'])->middleware(['premium', 'idempotency']);
        Route::get('exports/{id}', [DataExchangeController::class, 'exportStatus'])->middleware('premium');
        Route::get('exports/{id}/download', [DataExchangeController::class, 'download'])->middleware('premium');
        Route::post('imports/validate', [DataExchangeController::class, 'validateImport'])->middleware('premium');
        Route::post('imports', [DataExchangeController::class, 'import'])->middleware(['premium', 'idempotency']);
        Route::post('billing/apple/verify', [BillingController::class, 'verifyApple']);
        Route::post('billing/google/verify', [BillingController::class, 'verifyGoogle']);
        Route::post('billing/restore', [BillingController::class, 'restore']);
        Route::get('billing/entitlements', [BillingController::class, 'entitlements']);
        Route::post('privacy/{action}', [PrivacyController::class, 'request'])->whereIn('action', ['export', 'rectify', 'erase']);
        Route::get('privacy/requests/{id}/download',[PrivacyController::class, 'download']);
        Route::post('support/tickets',[SupportController::class, 'store']);
    });
});
