<?php
// app/Models/User.php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasSubscription;
use App\Models\Concerns\HasUsageTracking;
use App\Models\Concerns\HasTwoFactorAuth;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens,
        HasFactory,
        Notifiable,
        HasUuids,
        HasSubscription,
        HasUsageTracking,
        HasTwoFactorAuth;

    /**
     * Indicates if the model's ID is auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     */
    protected $keyType = "string";

    protected $fillable = [
        "name",
        "surname",
        "email",
        "password",
        "phone",
        "date_of_birth",
        "role",
        "is_active",
        "google_id",
        "avatar",
        "profile_photo_id",
        "company_id",
    ];

    protected $hidden = [
        "password",
        "remember_token",
        "two_factor_secret",
        "two_factor_recovery_codes",
    ];

    protected $casts = [
        "id" => "string",
        "email_verified_at" => "datetime",
        "date_of_birth" => "date",
        "role" => UserRole::class,
        "is_active" => "boolean",
        "is_admin" => "boolean",
        "two_factor_enabled" => "boolean",
        "two_factor_recovery_codes" => "array",
        "two_factor_confirmed_at" => "datetime",
        "api_calls_reset_at" => "datetime",
        "last_login_at" => "datetime",
        "company_id" => "integer",
    ];

    // Sanctum token configuration
    public function createToken(
        string $name,
        array $abilities = ["*"],
        \DateTimeInterface $expiresAt = null,
    ) {
        $token = $this->tokens()->create([
            "name" => $name,
            "token" => hash(
                "sha256",
                $plainTextToken = \Illuminate\Support\Str::random(40),
            ),
            "abilities" => $abilities,
            "expires_at" => $expiresAt,
        ]);

        return new \Laravel\Sanctum\NewAccessToken(
            $token,
            $token->getKey() . "|" . $plainTextToken,
        );
    }

    // Sanctum abilities for role-based access
    public function tokenCan(string $ability): bool
    {
        // Check if current token has the ability
        if (!$this->currentAccessToken()) {
            return false;
        }

        // Role-based abilities
        $roleAbilities = $this->getRoleAbilities();

        return in_array("*", $roleAbilities) ||
            in_array($ability, $roleAbilities);
    }

    public function getRoleAbilities(): array
    {
        return match ($this->role) {
            UserRole::FREE => ["timber:read", "profile:read", "profile:update"],
            UserRole::BASIC => [
                "timber:read",
                "timber:create",
                "timber:update-own",
                "profile:read",
                "profile:update",
                "company:read",
                "services:read",
                "api:access",
            ],
            UserRole::PREMIUM => [
                "timber:read",
                "timber:create",
                "timber:update-own",
                "timber:delete-own",
                "profile:read",
                "profile:update",
                "company:read",
                "company:create",
                "company:update-own",
                "services:read",
                "services:create",
                "services:update-own",
                "api:access",
                "analytics:read",
            ],
            UserRole::ENTERPRISE => ["*"],
            UserRole::ADMIN => ["*", "admin:*"],
        };
    }

    // Relationships
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function profilePhoto()
    {
        return $this->belongsTo(Photo::class, "profile_photo_id");
    }

    public function timberListings()
    {
        return $this->hasMany(Timber::class);
    }

    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function usageRecords()
    {
        return $this->hasMany(UsageRecord::class);
    }

    // Accessors & Mutators
    public function getFullNameAttribute(): string
    {
        return trim("{$this->name} {$this->surname}");
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar) {
            return $this->avatar;
        }

        if ($this->profilePhoto) {
            return $this->profilePhoto->url;
        }

        return $this->getGravatarUrl();
    }

    public function getRoleLabelAttribute(): string
    {
        return $this->role->label();
    }

    // Helper Methods
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN || $this->is_admin;
    }

    public function isPaid(): bool
    {
        return in_array($this->role, [
            UserRole::BASIC,
            UserRole::PREMIUM,
            UserRole::ENTERPRISE,
        ]);
    }

    public function canAccessFeature(string $feature): bool
    {
        $features = $this->role->getFeatures();
        return isset($features[$feature]) && $features[$feature];
    }

    public function getFeatureLimit(string $feature): int
    {
        $features = $this->role->getFeatures();
        return $features[$feature] ?? 0;
    }

    public function hasReachedLimit(string $feature): bool
    {
        $limit = $this->getFeatureLimit($feature);

        if ($limit === -1) {
            return false;
        } // Unlimited
        if ($limit === 0) {
            return true;
        } // Not allowed

        $current = $this->getCurrentUsage($feature);
        return $current >= $limit;
    }

    public function updateLastLogin(): void
    {
        $this->update([
            "last_login_at" => now(),
            "last_login_ip" => request()->ip(),
        ]);
    }

    public function markEmailAsVerified()
    {
        if (!$this->hasVerifiedEmail()) {
            $this->forceFill([
                "email_verified_at" => $this->freshTimestamp(),
            ])->save();
        }

        return $this;
    }

    // Sanctum token management helpers
    public function revokeAllTokens(): void
    {
        $this->tokens()->delete();
    }

    public function revokeCurrentToken(): void
    {
        $this->currentAccessToken()?->delete();
    }

    public function getActiveTokensCount(): int
    {
        return $this->tokens()
            ->where("expires_at", ">", now())
            ->orWhereNull("expires_at")
            ->count();
    }

    public function hasValidToken(): bool
    {
        return $this->currentAccessToken() !== null;
    }

    private function getGravatarUrl(): string
    {
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=200";
    }

    private function getCurrentUsage(string $feature): int
    {
        return match ($feature) {
            "timber_listings" => $this->timberListings()->count(),
            "services" => $this->company?->services()->count() ?? 0,
            default => 0,
        };
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where("is_active", true);
    }

    public function scopeByRole($query, UserRole $role)
    {
        return $query->where("role", $role);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull("email_verified_at");
    }

    public function scopePaidUsers($query)
    {
        return $query->whereIn("role", ["basic", "premium", "enterprise"]);
    }

    public function scopeWithValidTokens($query)
    {
        return $query->whereHas("tokens", function ($q) {
            $q->where("expires_at", ">", now())->orWhereNull("expires_at");
        });
    }
}
