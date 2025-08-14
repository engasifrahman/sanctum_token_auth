<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\v1\Auth\LoginController;
use App\Http\Controllers\API\v1\Auth\LogoutController;
use App\Http\Controllers\API\v1\Auth\PasswordController;
use App\Http\Controllers\API\v1\Auth\RegisterController;
use App\Http\Controllers\API\v1\Auth\EmailVerificationController;

Route::prefix('/v1')->group(function () {
    Route::get('/health', fn () => 'Health is good!');

    // Public Auth Routes
    Route::prefix('auth')->name('v1.auth.')->group(function () {
        Route::post('register', RegisterController::class)->name('register');
        Route::post('login', [LoginController::class, 'login'])->name('login');
        Route::post('forgot-password', [PasswordController::class, 'forgotPassword'])->name('password.forgot');
        Route::post('reset-password', [PasswordController::class, 'resetPassword'])->name('password.reset');
        Route::post('resend-verification-email', [EmailVerificationController::class, 'resendVerificationEmail'])->name('verification.resend');
        Route::post('verify-email/{id}/{hash}', [EmailVerificationController::class, 'verifyEmailLink'])->middleware('signed')->name('verification.verify');
    });

    // Protected Auth Routes
    Route::middleware(['auth:sanctum', 'verified'])->prefix('auth')->name('auth.')->group(function () {
        Route::post('refresh-token', [LoginController::class, 'refreshToken'])->name('token.refresh');
        Route::post('logout', LogoutController::class)->name('logout');
    });

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::middleware(['role:Admin | Super Admin'])->group(function () {
            Route::get('/admin', function (Request $request) {
                return $request->user();
            });
        });

        Route::middleware(['role:User'])->group(function () {
            Route::get('/user', function (Request $request) {
                return $request->user();
            });
        });

        Route::middleware(['role:Subscriber'])->group(function () {
            Route::get('/subscriber', function (Request $request) {
                return $request->user();
            });
        });
    });
});

