<?php

enum SubscriptionStatus: string
{
    case ACTIVE = "active";
    case CANCELLED = "cancelled";
    case PAST_DUE = "past_due";
    case UNPAID = "unpaid";
    case TRAILING = "trailing";
    case INCOMPLETE = "incomplete";
    case INCOMPLETE_EXPIRED = "incomplete_expired";

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => "Active",
            self::CANCELLED => "Cancelled",
            self::PAST_DUE => "Past Due",
            self::UNPAID => "Unpaid",
            self::TRAILING => "Trailing",
            self::INCOMPLETE => "Incomplete",
            self::INCOMPLETE_EXPIRED => "Incomplete Expired",
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::ACTIVE, self::TRAILING]);
    }
}
