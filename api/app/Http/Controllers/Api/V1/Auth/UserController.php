<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use App\DTOs\UpdateUserDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private AuthService $authService)
    {
        $this->middleware(["auth:sanctum", "token.valid"]);
    }

    public function show(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            "success" => true,
            "data" => [
                "user" => new UserResource(
                    $user->load(["profilePhoto", "company", "subscriptions"]),
                ),
            ],
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth()->user();
        $dto = UpdateUserDTO::fromRequest($request->validated());

        $user->update($dto->toArray());

        return response()->json([
            "success" => true,
            "data" => [
                "user" => new UserResource(
                    $user->fresh()->load(["profilePhoto", "company"]),
                ),
            ],
            "message" => "Profile updated successfully",
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                "current_password" => ["The current password is incorrect."],
            ]);
        }

        $user->update([
            "password" => Hash::make($request->password),
        ]);

        // Revoke all other tokens for security
        $currentToken = $user->currentAccessToken();
        $user->tokens()->where("id", "!=", $currentToken->id)->delete();

        return response()->json([
            "success" => true,
            "message" =>
                "Password updated successfully. Other sessions have been logged out.",
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            "password" => "required|string",
        ]);

        $user = auth()->user();

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                "password" => ["The password is incorrect."],
            ]);
        }

        // Delete all user tokens
        $user->revokeAllTokens();

        // Soft delete or hard delete based on your requirements
        $user->update(["is_active" => false]);
        // Or for hard delete: $user->delete();

        return response()->json([
            "success" => true,
            "message" => "Account deactivated successfully",
        ]);
    }

    public function usage(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            "success" => true,
            "data" => [
                "api_calls" => [
                    "this_hour" => $user->api_calls_this_hour,
                    "total" => $user->total_api_calls,
                    "limit" => $user->role->getRateLimit(),
                    "reset_at" => $user->api_calls_reset_at,
                ],
                "storage" => [
                    "used_bytes" => $user->storage_used_bytes,
                    "used_formatted" => $user->getStorageUsedFormatted(),
                    "limit_mb" => $user->role->getStorageLimit(),
                    "usage_percentage" => $user->getStorageUsagePercentage(),
                ],
                "features" => [
                    "timber_listings" => [
                        "used" => $user->timberListings()->count(),
                        "limit" => $user->getFeatureLimit("timber_listings"),
                    ],
                    "services" => [
                        "used" => $user->company?->services()->count() ?? 0,
                        "limit" => $user->getFeatureLimit("services"),
                    ],
                ],
            ],
        ]);
    }

    public function subscription(): JsonResponse
    {
        $user = auth()->user();
        $subscription = $user->activeSubscription;

        return response()->json([
            "success" => true,
            "data" => [
                "current_plan" => [
                    "role" => $user->role,
                    "label" => $user->role->label(),
                    "features" => $user->role->getFeatures(),
                ],
                "subscription" => $subscription
                    ? [
                        "id" => $subscription->id,
                        "status" => $subscription->stripe_status,
                        "trial_ends_at" => $subscription->trial_ends_at,
                        "ends_at" => $subscription->ends_at,
                        "on_trial" => $subscription->onTrial(),
                        "days_until_trial_ends" => $subscription->daysUntilTrialEnds(),
                    ]
                    : null,
                "active_tokens" => $user->getActiveTokensCount(),
            ],
        ]);
    }

    public function tokens(): JsonResponse
    {
        $user = auth()->user();
        $tokens = $user->tokens()->orderBy("last_used_at", "desc")->get();

        return response()->json([
            "success" => true,
            "data" => [
                "tokens" => $tokens->map(function ($token) {
                    return [
                        "id" => $token->id,
                        "name" => $token->name,
                        "abilities" => $token->abilities,
                        "last_used_at" => $token->last_used_at,
                        "created_at" => $token->created_at,
                        "expires_at" => $token->expires_at,
                        "is_current" =>
                            $token->id ===
                            auth()->user()->currentAccessToken()->id,
                        "is_expired" =>
                            $token->expires_at && $token->expires_at->isPast(),
                    ];
                }),
                "total_count" => $tokens->count(),
                "active_count" => $user->getActiveTokensCount(),
            ],
        ]);
    }
}
