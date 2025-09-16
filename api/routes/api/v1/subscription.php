<?php

// routes/api/v1/subscription.php (For future Stripe integration)

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Subscription\SubscriptionController;
use App\Http\Controllers\Api\V1\Subscription\PaymentController;
use App\Http\Controllers\Api\V1\Subscription\BillingController;

/*
|--------------------------------------------------------------------------
| Subscription & Billing Routes (Future Stripe Integration)
|--------------------------------------------------------------------------
*/

Route::middleware(["auth:sanctum", "token.valid"])->group(function () {
    // Subscription Plans (Public info)
    Route::get("plans", [SubscriptionController::class, "plans"])
        ->name("plans.index")
        ->withoutMiddleware(["auth:sanctum", "token.valid"]);
    Route::get("plans/{plan}", [SubscriptionController::class, "showPlan"])
        ->name("plans.show")
        ->withoutMiddleware(["auth:sanctum", "token.valid"]);

    // Subscription Management
    Route::prefix("subscription")
        ->name("subscription.")
        ->group(function () {
            Route::get("/", [SubscriptionController::class, "current"])->name(
                "current",
            );
            Route::post("/subscribe", [
                SubscriptionController::class,
                "subscribe",
            ])->name("subscribe");
            Route::post("/change-plan", [
                SubscriptionController::class,
                "changePlan",
            ])->name("change");
            Route::post("/cancel", [
                SubscriptionController::class,
                "cancel",
            ])->name("cancel");
            Route::post("/resume", [
                SubscriptionController::class,
                "resume",
            ])->name("resume");
            Route::get("/invoice/{invoice}", [
                SubscriptionController::class,
                "invoice",
            ])->name("invoice");
            Route::get("/invoices", [
                SubscriptionController::class,
                "invoices",
            ])->name("invoices");
        });

    // Payment Methods
    Route::prefix("payment-methods")
        ->name("payment.")
        ->group(function () {
            Route::get("/", [PaymentController::class, "index"])->name(
                "methods.index",
            );
            Route::post("/", [PaymentController::class, "store"])->name(
                "methods.store",
            );
            Route::put("/{paymentMethod}/default", [
                PaymentController::class,
                "setDefault",
            ])->name("methods.default");
            Route::delete("/{paymentMethod}", [
                PaymentController::class,
                "destroy",
            ])->name("methods.delete");
        });

    // Billing
    Route::prefix("billing")
        ->name("billing.")
        ->group(function () {
            Route::get("/", [BillingController::class, "show"])->name("show");
            Route::put("/", [BillingController::class, "update"])->name(
                "update",
            );
            Route::get("/portal", [BillingController::class, "portal"])->name(
                "portal",
            );
        });
});
