<?php

// routes/api/v1/admin.php (Admin routes - requires admin role)

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\AdminSystemController;

/*
|--------------------------------------------------------------------------
| Admin Routes (Requires admin role)
|--------------------------------------------------------------------------
*/

Route::middleware(["auth:sanctum", "token.valid", "role:admin"])->group(
    function () {
        // User Management
        Route::prefix("users")
            ->name("admin.users.")
            ->group(function () {
                Route::get("/", [AdminUserController::class, "index"])->name(
                    "index",
                );
                Route::get("/{user}", [
                    AdminUserController::class,
                    "show",
                ])->name("show");
                Route::put("/{user}", [
                    AdminUserController::class,
                    "update",
                ])->name("update");
                Route::delete("/{user}", [
                    AdminUserController::class,
                    "destroy",
                ])->name("destroy");
                Route::post("/{user}/ban", [
                    AdminUserController::class,
                    "ban",
                ])->name("ban");
                Route::post("/{user}/unban", [
                    AdminUserController::class,
                    "unban",
                ])->name("unban");
                Route::post("/{user}/impersonate", [
                    AdminUserController::class,
                    "impersonate",
                ])->name("impersonate");
                Route::get("/{user}/activity", [
                    AdminUserController::class,
                    "activity",
                ])->name("activity");
                Route::post("/{user}/reset-password", [
                    AdminUserController::class,
                    "resetPassword",
                ])->name("reset.password");
                Route::post("/bulk-action", [
                    AdminUserController::class,
                    "bulkAction",
                ])->name("bulk.action");
            });

        // Role Management
        Route::prefix("roles")
            ->name("admin.roles.")
            ->group(function () {
                Route::get("/", [AdminRoleController::class, "index"])->name(
                    "index",
                );
                Route::post("/", [AdminRoleController::class, "store"])->name(
                    "store",
                );
                Route::get("/{role}", [
                    AdminRoleController::class,
                    "show",
                ])->name("show");
                Route::put("/{role}", [
                    AdminRoleController::class,
                    "update",
                ])->name("update");
                Route::delete("/{role}", [
                    AdminRoleController::class,
                    "destroy",
                ])->name("destroy");
            });

        // System Management
        Route::prefix("system")
            ->name("admin.system.")
            ->group(function () {
                Route::get("stats", [
                    AdminSystemController::class,
                    "stats",
                ])->name("stats");
                Route::get("logs", [
                    AdminSystemController::class,
                    "logs",
                ])->name("logs");
                Route::post("cache/clear", [
                    AdminSystemController::class,
                    "clearCache",
                ])->name("cache.clear");
                Route::get("health", [
                    AdminSystemController::class,
                    "healthCheck",
                ])->name("health");
                Route::post("maintenance/enable", [
                    AdminSystemController::class,
                    "enableMaintenance",
                ])->name("maintenance.enable");
                Route::post("maintenance/disable", [
                    AdminSystemController::class,
                    "disableMaintenance",
                ])->name("maintenance.disable");
            });
    },
);
