<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\v1\Auth\LoginController;
use App\Http\Controllers\API\v1\Auth\LogoutController;
use App\Http\Controllers\API\v1\Auth\RegisterController;
use App\Http\Controllers\API\v1\Auth\ResetPasswordController;
use App\Http\Controllers\API\v1\Auth\ForgotPasswordController;
use App\Http\Controllers\API\v1\Auth\EmailVerificationController;

Route::prefix('/v1')->group(function () {
    Route::get('/health', fn () => 'Health is good!');

    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [LoginController::class, 'login'])->name('login');
    Route::post('/resend-verification-mail', [EmailVerificationController::class, 'resend']);
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendPasswordResetLinkEmail']);
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.reset');

    Route::middleware('signed')->group(function () {
        Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');
    });

    Route::middleware(['auth:sanctum', 'verified'])->group(function () {
        Route::post('/logout', [LogoutController::class, 'logout']);

        Route::get('/user', function (Request $request) {
            return 'Hi';
            return $request->user();
        });
    });
});

