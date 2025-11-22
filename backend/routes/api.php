<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/* Health check (pública) */
Route::get('/health', fn () => response()->json(['status' => 'ok']));

/* Pings por rol (requieren JWT y rol correcto) */
Route::middleware(['jwt.auth', 'role:admin'])
    ->get('/admin/panel', fn () => response()->json(['ok' => true, 'role' => 'admin']));

Route::middleware(['jwt.auth', 'role:juez'])
    ->get('/juez/panel', fn () => response()->json(['ok' => true, 'role' => 'juez']));

Route::middleware(['jwt.auth', 'role:competidor'])
    ->get('/competidor/panel', fn () => response()->json(['ok' => true, 'role' => 'competidor']));

/* Auth */
Route::prefix('auth')->group(function () {
    // Públicas
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    // Protegidas con token vigente
    Route::middleware('jwt.auth')->group(function () {
        Route::get('/me',      [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // Refresh SIN middleware
    Route::post('/refresh', [AuthController::class, 'refresh']);
});
