<?php

declare(strict_types=1);

use Corvant\Infrastructure\Http\Controllers\AuthController;
use Corvant\Infrastructure\Http\Controllers\MfaController;
use Corvant\Infrastructure\Http\Controllers\PermissionController;
use Corvant\Infrastructure\Http\Controllers\RoleController;
use Corvant\Infrastructure\Http\Controllers\SessionController;
use Corvant\Infrastructure\Http\Controllers\TenantController;
use Corvant\Infrastructure\Http\Controllers\UserController;
use Corvant\Infrastructure\Http\Middleware\AuthenticateSessionMiddleware;
use Corvant\Infrastructure\Http\Middleware\ResolveTenantMiddleware;
use Illuminate\Support\Facades\Route;

Route::get('tenants/current', [TenantController::class, 'current'])
    ->middleware(ResolveTenantMiddleware::class);

Route::post('tenants', [TenantController::class, 'store'])
    ->middleware(AuthenticateSessionMiddleware::class);

Route::get('users/{userId}/tenants', [TenantController::class, 'forUser']);

Route::middleware([AuthenticateSessionMiddleware::class])->prefix('users/me')->group(function (): void {
    Route::get('/', [UserController::class, 'me']);
    Route::put('/', [UserController::class, 'update']);
    Route::put('email', [UserController::class, 'updateEmail']);
    Route::post('email/confirm', [UserController::class, 'confirmEmail']);
    Route::put('phone', [UserController::class, 'updatePhone']);
    Route::put('password', [UserController::class, 'updatePassword']);
    Route::delete('/', [UserController::class, 'destroy']);
    Route::get('audit', [UserController::class, 'audit']);
});

Route::prefix('mfa')->group(function (): void {
    Route::post('totp/verify', [MfaController::class, 'verify']);
    Route::post('recovery-codes/use', [MfaController::class, 'useRecoveryCode']);

    Route::middleware([AuthenticateSessionMiddleware::class])->group(function (): void {
        Route::post('totp/enable', [MfaController::class, 'enable']);
        Route::post('totp/confirm', [MfaController::class, 'confirm']);
        Route::post('totp/disable', [MfaController::class, 'disable']);
        Route::get('recovery-codes', [MfaController::class, 'recoveryCodes']);
    });
});

Route::middleware([AuthenticateSessionMiddleware::class])->prefix('sessions')->group(function (): void {
    Route::get('/', [SessionController::class, 'index']);
    Route::delete('/', [SessionController::class, 'destroyOthers']);
    Route::delete('{id}', [SessionController::class, 'destroy']);
});

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:corvant-login');
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::post('verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('resend-verification', [AuthController::class, 'resendVerification'])
        ->middleware(AuthenticateSessionMiddleware::class);
});

Route::middleware([
    AuthenticateSessionMiddleware::class,
    ResolveTenantMiddleware::class,
])->group(function (): void {
    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::put('roles/{id}', [RoleController::class, 'update']);
    Route::delete('roles/{id}', [RoleController::class, 'destroy']);
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::post('users/{userId}/roles', [RoleController::class, 'assignRole']);
    Route::delete('users/{userId}/roles/{roleId}', [RoleController::class, 'revokeRole']);
});
