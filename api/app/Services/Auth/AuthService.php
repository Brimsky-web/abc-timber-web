<?php
// app/Services/Auth/AuthService.php

namespace App\Services\Auth;

use App\DTOs\RegisterUserDTO;
use App\DTOs\LoginUserDTO;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\SocialAccount;
use App\Services\BaseService;
use App\Services\Email\EmailVerificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthService extends BaseService
{
    public function __construct(
        private EmailVerificationService $emailService,
    ) {}

    public function register(RegisterUserDTO $dto): array
    {
        try {
            $user = User::create([
                "name" => $dto->name,
                "surname" => $dto->surname,
                "email" => $dto->email,
                "password" => Hash::make($dto->password),
                "phone" => $dto->phone,
                "date_of_birth" => $dto->dateOfBirth,
                "role" => UserRole::FREE,
                "is_active" => true,
            ]);

            // Send email verification
            $this->emailService->sendVerificationEmail($user);

            // Fire registered event
            event(new Registered($user));

            // Create Sanctum token with appropriate abilities
            $token = $user->createToken(
                name: "auth-token",
                abilities: $user->getRoleAbilities(),
                expiresAt: now()->addDays(30),
            );

            return [
                "user" => $user->load(["profilePhoto", "company"]),
                "token" => $token->plainTextToken,
                "token_type" => "Bearer",
                "expires_at" => $token->accessToken->expires_at,
                "abilities" => $user->getRoleAbilities(),
                "message" =>
                    "Registration successful. Please verify your email.",
            ];
        } catch (\Exception $e) {
            $this->handleException($e, "Registration failed");
        }
    }

    public function login(LoginUserDTO $dto): array
    {
        try {
            $user = User::where("email", $dto->email)->first();

            if (!$user || !Hash::check($dto->password, $user->password)) {
                throw ValidationException::withMessages([
                    "email" => ["The provided credentials are incorrect."],
                ]);
            }

            if (!$user->is_active) {
                throw ValidationException::withMessages([
                    "email" => ["Your account has been deactivated."],
                ]);
            }

            // Check if 2FA is required
            if (
                $user->two_factor_enabled &&
                !$dto->twoFactorCode &&
                !$dto->recoveryCode
            ) {
                return [
                    "requires_2fa" => true,
                    "message" => "Two-factor authentication code required.",
                ];
            }

            // Validate 2FA if provided
            if ($user->two_factor_enabled) {
                $isValid = false;

                if ($dto->twoFactorCode) {
                    $isValid = $user->validateTwoFactorCode(
                        $dto->twoFactorCode,
                    );
                } elseif ($dto->recoveryCode) {
                    $isValid = $user->validateRecoveryCode($dto->recoveryCode);
                }

                if (!$isValid) {
                    throw ValidationException::withMessages([
                        "two_factor_code" => ["Invalid authentication code."],
                    ]);
                }
            }

            // Revoke all existing tokens for security (optional)
            // $user->revokeAllTokens();

            // Update last login
            $user->updateLastLogin();

            // Create new Sanctum token with role-based abilities
            $tokenName = "auth-token-" . request()->userAgent();
            $token = $user->createToken(
                name: $tokenName,
                abilities: $user->getRoleAbilities(),
                expiresAt: now()->addDays(30),
            );

            return [
                "user" => $user->load(["profilePhoto", "company"]),
                "token" => $token->plainTextToken,
                "token_type" => "Bearer",
                "expires_at" => $token->accessToken->expires_at,
                "abilities" => $user->getRoleAbilities(),
                "message" => "Login successful.",
            ];
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            $this->handleException($e, "Login failed");
        }
    }

    public function logout(): void
    {
        try {
            $user = Auth::user();

            // Revoke current token only
            $user->revokeCurrentToken();

            // Or revoke all tokens for complete logout
            // $user->revokeAllTokens();
        } catch (\Exception $e) {
            $this->handleException($e, "Logout failed");
        }
    }

    public function refreshToken(): array
    {
        try {
            $user = Auth::user();

            // Revoke current token
            $currentToken = $user->currentAccessToken();
            $currentToken?->delete();

            // Create new token with updated abilities (in case role changed)
            $token = $user->createToken(
                name: "refreshed-auth-token",
                abilities: $user->getRoleAbilities(),
                expiresAt: now()->addDays(30),
            );

            return [
                "user" => $user->load(["profilePhoto", "company"]),
                "token" => $token->plainTextToken,
                "token_type" => "Bearer",
                "expires_at" => $token->accessToken->expires_at,
                "abilities" => $user->getRoleAbilities(),
                "message" => "Token refreshed successfully.",
            ];
        } catch (\Exception $e) {
            $this->handleException($e, "Token refresh failed");
        }
    }

    public function revokeAllTokens(): void
    {
        try {
            $user = Auth::user();
            $user->revokeAllTokens();
        } catch (\Exception $e) {
            $this->handleException($e, "Failed to revoke tokens");
        }
    }

    public function handleSocialLogin(
        string $provider,
        SocialiteUser $socialUser,
    ): array {
        try {
            // Check if social account exists
            $socialAccount = SocialAccount::where([
                "provider" => $provider,
                "provider_id" => $socialUser->getId(),
            ])->first();

            if ($socialAccount) {
                $user = $socialAccount->user;
            } else {
                // Check if user exists by email
                $user = User::where("email", $socialUser->getEmail())->first();

                if ($user) {
                    // Link social account to existing user
                    $user->socialAccounts()->create([
                        "provider" => $provider,
                        "provider_id" => $socialUser->getId(),
                        "provider_email" => $socialUser->getEmail(),
                        "provider_data" => [
                            "name" => $socialUser->getName(),
                            "avatar" => $socialUser->getAvatar(),
                        ],
                    ]);
                } else {
                    // Create new user
                    $user = $this->createUserFromSocial($provider, $socialUser);
                }
            }

            // Update avatar if available
            if ($socialUser->getAvatar() && !$user->avatar) {
                $user->update(["avatar" => $socialUser->getAvatar()]);
            }

            // Update last login
            $user->updateLastLogin();

            // Create Sanctum token
            $token = $user->createToken(
                name: "social-auth-{$provider}",
                abilities: $user->getRoleAbilities(),
                expiresAt: now()->addDays(30),
            );

            return [
                "user" => $user->load(["profilePhoto", "company"]),
                "token" => $token->plainTextToken,
                "token_type" => "Bearer",
                "expires_at" => $token->accessToken->expires_at,
                "abilities" => $user->getRoleAbilities(),
                "message" => "Social login successful.",
            ];
        } catch (\Exception $e) {
            $this->handleException($e, "Social login failed");
        }
    }

    public function sendPasswordResetLink(string $email): void
    {
        try {
            $user = User::where("email", $email)->first();

            if (!$user) {
                // Don't reveal if user exists
                return;
            }

            $this->emailService->sendPasswordResetEmail($user);
        } catch (\Exception $e) {
            $this->handleException($e, "Failed to send password reset email");
        }
    }

    public function resetPassword(
        string $token,
        string $email,
        string $password,
    ): void {
        try {
            $resetToken = \DB::table("password_reset_tokens")
                ->where("email", $email)
                ->where("token", hash("sha256", $token))
                ->where("used", false)
                ->where("expires_at", ">", now())
                ->first();

            if (!$resetToken) {
                throw ValidationException::withMessages([
                    "token" => ["Invalid or expired password reset token."],
                ]);
            }

            // Update password
            $user = User::where("email", $email)->first();
            $user->update(["password" => Hash::make($password)]);

            // Mark token as used
            \DB::table("password_reset_tokens")
                ->where("id", $resetToken->id)
                ->update(["used" => true]);

            // Revoke all Sanctum tokens for security
            $user->revokeAllTokens();
        } catch (\Exception $e) {
            if ($e instanceof ValidationException) {
                throw $e;
            }
            $this->handleException($e, "Password reset failed");
        }
    }

    public function upgradeUserRole(User $user, UserRole $newRole): array
    {
        try {
            $user->update(["role" => $newRole]);

            // Create new token with updated abilities
            $user->revokeAllTokens(); // Revoke old tokens

            $token = $user->createToken(
                name: "role-upgrade-token",
                abilities: $user->getRoleAbilities(),
                expiresAt: now()->addDays(30),
            );

            return [
                "user" => $user->fresh()->load(["profilePhoto", "company"]),
                "token" => $token->plainTextToken,
                "abilities" => $user->getRoleAbilities(),
                "message" => "Successfully upgraded to {$newRole->label()}",
            ];
        } catch (\Exception $e) {
            $this->handleException($e, "Role upgrade failed");
        }
    }

    private function createUserFromSocial(
        string $provider,
        SocialiteUser $socialUser,
    ): User {
        $nameParts = explode(" ", $socialUser->getName() ?: "", 2);

        $user = User::create([
            "name" => $nameParts[0] ?? "User",
            "surname" => $nameParts[1] ?? null,
            "email" => $socialUser->getEmail(),
            "password" => Hash::make(\Str::random(32)), // Random password
            "role" => UserRole::FREE,
            "is_active" => true,
            "email_verified_at" => now(), // Social accounts are pre-verified
            "avatar" => $socialUser->getAvatar(),
        ]);

        // Create social account link
        $user->socialAccounts()->create([
            "provider" => $provider,
            "provider_id" => $socialUser->getId(),
            "provider_email" => $socialUser->getEmail(),
            "provider_data" => [
                "name" => $socialUser->getName(),
                "avatar" => $socialUser->getAvatar(),
            ],
        ]);

        return $user;
    }
}
