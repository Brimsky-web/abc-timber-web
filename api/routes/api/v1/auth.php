<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\SocialAuthController;
use App\Http\Controllers\Api\V1\Auth\TwoFactorController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

// Public Authentication Routes
Route::post("register", [AuthController::class, "register"])->name("register");
Route::post("login", [AuthController::class, "login"])->name("login");
Route::post("forgot-password", [AuthController::class, "forgotPassword"])->name(
    "forgot-password",
);
Route::post("reset-password", [AuthController::class, "resetPassword"])->name(
    "reset-password",
);

// Email Verification (public routes)
Route::prefix("email")
    ->name("email.")
    ->group(function () {
        Route::post("verify", [
            EmailVerificationController::class,
            "verify",
        ])->name("verify");
        Route::get("verify/{token}", [
            EmailVerificationController::class,
            "verifyFromLink",
        ])->name("verify.link");
    });

// Social Authentication (OAuth)
Route::prefix("social")
    ->name("social.")
    ->group(function () {
        Route::get("{provider}/redirect", [
            SocialAuthController::class,
            "redirectToProvider",
        ])->name("redirect");
        Route::get("{provider}/callback", [
            SocialAuthController::class,
            "handleProviderCallback",
        ])->name("callback");
        Route::post("{provider}/token", [
            SocialAuthController::class,
            "handleTokenCallback",
        ])->name("token");
    });

// Two-Factor Authentication (public routes for login process)
Route::prefix("2fa")
    ->name("2fa.")
    ->group(function () {
        Route::post("verify", [
            TwoFactorController::class,
            "verifyLogin",
        ])->name("verify.login");
        Route::post("verify-recovery", [
            TwoFactorController::class,
            "verifyRecoveryCode",
        ])->name("verify.recovery");
    });

// Protected Routes (Require Authentication)
Route::middleware(["auth:sanctum", "token.valid"])->group(function () {
    // User Info & Logout
    Route::get("me", [AuthController::class, "me"])->name("me");
    Route::post("logout", [AuthController::class, "logout"])->name("logout");
    Route::post("logout-all", [AuthController::class, "logoutAll"])->name(
        "logout.all",
    );
    Route::post("refresh", [AuthController::class, "refresh"])->name("refresh");

    // Password Management
    Route::post("change-password", [
        AuthController::class,
        "changePassword",
    ])->name("change-password");

    // Email Verification (authenticated routes)
    Route::prefix("email")
        ->name("email.")
        ->group(function () {
            Route::post("resend", [
                EmailVerificationController::class,
                "resend",
            ])->name("resend");
            Route::get("status", [
                EmailVerificationController::class,
                "status",
            ])->name("status");
        });

    // Two-Factor Authentication (authenticated routes)
    Route::prefix("2fa")
        ->name("2fa.")
        ->group(function () {
            Route::get("status", [TwoFactorController::class, "status"])->name(
                "status",
            );
            Route::post("enable", [TwoFactorController::class, "enable"])->name(
                "enable",
            );
            Route::post("confirm", [
                TwoFactorController::class,
                "confirm",
            ])->name("confirm");
            Route::delete("disable", [
                TwoFactorController::class,
                "disable",
            ])->name("disable");
            Route::post("recovery-codes", [
                TwoFactorController::class,
                "recoveryCodes",
            ])->name("recovery-codes");
            Route::get("qr-code", [TwoFactorController::class, "qrCode"])->name(
                "qr-code",
            );
        });

    // Account Security
    Route::prefix("security")
        ->name("security.")
        ->group(function () {
            Route::get("sessions", [
                AuthController::class,
                "activeSessions",
            ])->name("sessions");
            Route::delete("sessions/{tokenId}", [
                AuthController::class,
                "revokeSession",
            ])->name("sessions.revoke");
            Route::get("activity", [
                AuthController::class,
                "loginActivity",
            ])->name("activity");
        });
});
