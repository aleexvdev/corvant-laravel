<?php

declare(strict_types=1);

use Corvant\Infrastructure\Http\Controllers\AuthController;
use Corvant\Infrastructure\Http\Controllers\TenantController;
use Corvant\Infrastructure\Http\Middleware\ResolveTenantMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('tenants/current', [TenantController::class, 'current'])
    ->middleware(ResolveTenantMiddleware::class);

Route::get('users/{userId}/tenants', [TenantController::class, 'forUser']);

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
});
