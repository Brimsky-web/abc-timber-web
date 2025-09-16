<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\User\UserController;
use App\Http\Controllers\Api\V1\User\NotificationController;
use App\Http\Controllers\Api\V1\User\PreferenceController;

/*
|--------------------------------------------------------------------------
| User Management Routes
|--------------------------------------------------------------------------
*/

// All user routes require authentication
Route::middleware(["auth:sanctum", "token.valid"])->group(function () {
    // Profile Management
    Route::prefix("profile")
        ->name("profile.")
        ->group(function () {
            Route::get("/", [UserController::class, "show"])->name("show");
            Route::put("/", [UserController::class, "update"])->name("update");
            Route::post("avatar", [
                UserController::class,
                "uploadAvatar",
            ])->name("avatar.upload");
            Route::delete("avatar", [
                UserController::class,
                "deleteAvatar",
            ])->name("avatar.delete");
        });

    // Account Management
    Route::prefix("account")
        ->name("account.")
        ->group(function () {
            Route::put("password", [
                UserController::class,
                "updatePassword",
            ])->name("password.update");
            Route::delete("/", [UserController::class, "destroy"])->name(
                "delete",
            );
            Route::post("deactivate", [
                UserController::class,
                "deactivate",
            ])->name("deactivate");
            Route::post("reactivate", [
                UserController::class,
                "reactivate",
            ])->name("reactivate");
        });

    // Usage & Subscription Info
    Route::prefix("subscription")
        ->name("subscription.")
        ->group(function () {
            Route::get("/", [UserController::class, "subscription"])->name(
                "show",
            );
            Route::get("usage", [UserController::class, "usage"])->name(
                "usage",
            );
            Route::get("limits", [UserController::class, "limits"])->name(
                "limits",
            );
        });

    // Token Management
    Route::prefix("tokens")
        ->name("tokens.")
        ->group(function () {
            Route::get("/", [UserController::class, "tokens"])->name("list");
            Route::post("/", [UserController::class, "createToken"])->name(
                "create",
            );
            Route::delete("/{tokenId}", [
                UserController::class,
                "revokeToken",
            ])->name("revoke");
            Route::delete("/all", [
                UserController::class,
                "revokeAllTokens",
            ])->name("revoke.all");
        });

    // Notifications
    Route::prefix("notifications")
        ->name("notifications.")
        ->group(function () {
            Route::get("/", [NotificationController::class, "index"])->name(
                "index",
            );
            Route::get("/unread", [
                NotificationController::class,
                "unread",
            ])->name("unread");
            Route::put("/{id}/read", [
                NotificationController::class,
                "markAsRead",
            ])->name("mark.read");
            Route::post("/mark-all-read", [
                NotificationController::class,
                "markAllAsRead",
            ])->name("mark.all.read");
            Route::delete("/{id}", [
                NotificationController::class,
                "destroy",
            ])->name("delete");
            Route::post("/clear-all", [
                NotificationController::class,
                "clearAll",
            ])->name("clear.all");
        });

    // User Preferences
    Route::prefix("preferences")
        ->name("preferences.")
        ->group(function () {
            Route::get("/", [PreferenceController::class, "show"])->name(
                "show",
            );
            Route::put("/", [PreferenceController::class, "update"])->name(
                "update",
            );
            Route::put("/privacy", [
                PreferenceController::class,
                "updatePrivacy",
            ])->name("privacy.update");
            Route::put("/notifications", [
                PreferenceController::class,
                "updateNotifications",
            ])->name("notifications.update");
        });

    // Data Export/Import
    Route::prefix("data")
        ->name("data.")
        ->group(function () {
            Route::post("export", [UserController::class, "exportData"])->name(
                "export",
            );
            Route::get("export/{exportId}/download", [
                UserController::class,
                "downloadExport",
            ])->name("export.download");
        });
});
