<?php

// routes/api.php (Main API routes file)

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// API Versioning
Route::prefix("v1")
    ->name("api.v1.")
    ->group(function () {
        // Authentication routes
        Route::prefix("auth")
            ->name("auth.")
            ->group(base_path("routes/api/v1/auth.php"));

        // User routes
        Route::prefix("user")
            ->name("user.")
            ->group(base_path("routes/api/v1/user.php"));

        // Subscription routes (for future Stripe integration)
        Route::prefix("subscription")
            ->name("subscription.")
            ->group(base_path("routes/api/v1/subscription.php"));

        // Admin routes
        Route::prefix("admin")
            ->name("admin.")
            ->group(base_path("routes/api/v1/admin.php"));

        // Health check route
        Route::get("health", function () {
            return response()->json([
                "status" => "ok",
                "timestamp" => now()->toISOString(),
                "version" => config("app.version", "1.0.0"),
            ]);
        })->name("health");
    });

// API Rate Limiting
Route::middleware(["throttle:api"])->group(function () {
    // All API routes are automatically rate limited
});

/*
|--------------------------------------------------------------------------
| Route Summary
|--------------------------------------------------------------------------
|
| Authentication Routes (24 routes):
| - POST /api/v1/auth/register
| - POST /api/v1/auth/login
| - POST /api/v1/auth/logout
| - POST /api/v1/auth/logout-all
| - POST /api/v1/auth/refresh
| - POST /api/v1/auth/forgot-password
| - POST /api/v1/auth/reset-password
| - POST /api/v1/auth/change-password
| - GET /api/v1/auth/me
| - Social Auth: 6 routes
| - Email Verification: 4 routes
| - Two-Factor Auth: 8 routes
| - Security: 4 routes
|
| User Routes (23 routes):
| - Profile: 4 routes
| - Account: 4 routes
| - Subscription: 3 routes
| - Tokens: 4 routes
| - Notifications: 6 routes
| - Preferences: 4 routes
| - Data: 2 routes
|
| Subscription Routes (13 routes - Future):
| - Plans: 2 routes
| - Subscription: 6 routes
| - Payment Methods: 4 routes
| - Billing: 3 routes
|
| Admin Routes (21 routes):
| - User Management: 10 routes
| - Role Management: 5 routes
| - System Management: 6 routes
|
| Total Routes: ~81 routes
|--------------------------------------------------------------------------
*/
