<?php

use App\Http\Controllers\Api\SyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Offline Sync
|--------------------------------------------------------------------------
|
| These routes handle data synchronization for the PWA offline mode.
| Protected by Sanctum authentication (session-based for same-domain SPA).
|
*/

Route::middleware('auth:sanctum')->prefix('sync')->group(function () {
    Route::get('bootstrap', [SyncController::class, 'bootstrap']);
    Route::get('delta', [SyncController::class, 'delta']);
    Route::post('push', [SyncController::class, 'push']);
    Route::get('status', [SyncController::class, 'status']);
});

// Public status endpoint (no auth needed, used by SW for connectivity check)
Route::get('ping', fn() => response()->json(['online' => true, 'time' => now()->toISOString()]));
