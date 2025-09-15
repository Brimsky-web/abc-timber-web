<?php

enum UserRole: string
{
    case FREE = "free";
    case PREMIUM = "premium";
    case ENTERPRISE = "enterprise";
    case ADMIN = "admin";

    public function label(): string
    {
        return match ($this) {
            self::FREE => "Free",
            self::PREMIUM => "Premium",
            self::ENTERPRISE => "Enterprise",
            self::ADMIN => "Admin",
        };
    }

    public function getFeatures(): array
    {
        return config("subscriptions.features.{$this->value}", []);
    }

    public function getEateLimit(): int
    {
        return config("api.rate_limits.{$this->value}.limit", 100);
    }

    public function getStorageLimit(): int
    {
        return config("api.storage_limits.{$this->value}.limit", 10);
    }
}
