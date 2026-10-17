<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Http\Controllers\CurrentUserController;
use Modules\Identity\Http\Controllers\RoleController;
use Modules\Identity\Http\Controllers\SessionController;
use Modules\Identity\Http\Controllers\UserController;
use Modules\Identity\Http\Middleware\EnsureActiveAccount;

Route::post('sessions', [SessionController::class, 'store'])
    ->middleware('throttle:identity-login')
    ->name('sessions.store');

Route::middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function (): void {
    Route::delete('sessions/current', [SessionController::class, 'destroy'])->name('sessions.destroy');
    Route::get('users/me', [CurrentUserController::class, 'show'])->name('users.me');

    Route::middleware('can:manage-users')->group(function (): void {
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{userId}', [UserController::class, 'show'])->whereNumber('userId')->name('users.show');
        Route::patch('users/{userId}', [UserController::class, 'update'])->whereNumber('userId')->name('users.update');
        Route::post('users/{userId}:assignRole', [UserController::class, 'assignRole'])->whereNumber('userId')->name('users.assignRole');
        Route::post('users/{userId}:deactivate', [UserController::class, 'deactivate'])->whereNumber('userId')->name('users.deactivate');
        Route::post('users/{userId}:activate', [UserController::class, 'activate'])->whereNumber('userId')->name('users.activate');
    });
});
