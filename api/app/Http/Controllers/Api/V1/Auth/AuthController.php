<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use App\DTOs\RegisterUserDTO;
use App\DTOs\LoginUserDTO;
use App\DTOs\PasswordResetDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $dto = RegisterUserDTO::fromRequest($request->validated());
        $result = $this->authService->register($dto);

        return response()->json(
            [
                "success" => true,
                "data" => [
                    "user" => new UserResource($result["user"]),
                    "token" => $result["token"],
                    "token_type" => $result["token_type"],
                    "expires_at" => $result["expires_at"],
                    "abilities" => $result["abilities"],
                ],
                "message" => $result["message"],
            ],
            201,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $dto = LoginUserDTO::fromRequest($request->validated());
        $result = $this->authService->login($dto);

        // Handle 2FA requirement
        if (isset($result["requires_2fa"])) {
            return response()->json(
                [
                    "success" => false,
                    "requires_2fa" => true,
                    "message" => $result["message"],
                ],
                200,
            );
        }

        return response()->json([
            "success" => true,
            "data" => [
                "user" => new UserResource($result["user"]),
                "token" => $result["token"],
                "token_type" => $result["token_type"],
                "expires_at" => $result["expires_at"],
                "abilities" => $result["abilities"],
            ],
            "message" => $result["message"],
        ]);
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            "success" => true,
            "message" => "Successfully logged out",
        ]);
    }

    public function logoutAll(): JsonResponse
    {
        $this->authService->revokeAllTokens();

        return response()->json([
            "success" => true,
            "message" => "Successfully logged out from all devices",
        ]);
    }

    public function refreshToken(): JsonResponse
    {
        $result = $this->authService->refreshToken();

        return response()->json([
            "success" => true,
            "data" => [
                "user" => new UserResource($result["user"]),
                "token" => $result["token"],
                "token_type" => $result["token_type"],
                "expires_at" => $result["expires_at"],
                "abilities" => $result["abilities"],
            ],
            "message" => $result["message"],
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->authService->sendPasswordResetLink($request->email);

        return response()->json([
            "success" => true,
            "message" =>
                "If an account with that email exists, we sent you a password reset link.",
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $dto = PasswordResetDTO::fromRequest($request->validated());
        $this->authService->resetPassword(
            $dto->token,
            $dto->email,
            $dto->password,
        );

        return response()->json([
            "success" => true,
            "message" =>
                "Your password has been reset successfully. Please log in with your new password.",
        ]);
    }

    public function me(): JsonResponse
    {
        $user = auth()->user();
        $token = $user->currentAccessToken();

        return response()->json([
            "success" => true,
            "data" => [
                "user" => new UserResource(
                    $user->load(["profilePhoto", "company"]),
                ),
                "token_info" => [
                    "name" => $token?->name,
                    "abilities" => $token?->abilities ?? [],
                    "expires_at" => $token?->expires_at,
                    "last_used_at" => $token?->last_used_at,
                ],
                "active_tokens_count" => $user->getActiveTokensCount(),
            ],
        ]);
    }

    public function tokenInfo(): JsonResponse
    {
        $user = auth()->user();
        $token = $user->currentAccessToken();

        if (!$token) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "No active token found",
                ],
                401,
            );
        }

        return response()->json([
            "success" => true,
            "data" => [
                "token_name" => $token->name,
                "abilities" => $token->abilities,
                "created_at" => $token->created_at,
                "last_used_at" => $token->last_used_at,
                "expires_at" => $token->expires_at,
                "is_expired" =>
                    $token->expires_at && $token->expires_at->isPast(),
                "active_tokens_count" => $user->getActiveTokensCount(),
            ],
        ]);
    }

    public function revokeToken(Request $request): JsonResponse
    {
        $request->validate([
            "token_id" => "required|integer|exists:personal_access_tokens,id",
        ]);

        $user = auth()->user();
        $token = $user->tokens()->where("id", $request->token_id)->first();

        if (!$token) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Token not found or does not belong to you",
                ],
                404,
            );
        }

        $token->delete();

        return response()->json([
            "success" => true,
            "message" => "Token revoked successfully",
        ]);
    }
}
