<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    use HasFactory;

    protected $casts = [
        "trial_ends_at" => "datetime",
        "ends_at" => "datetime",
        "quantity" => "integer",
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(
            SubscriptionPlan::class,
            "subscription_plan_id",
        );
    }

    public function items()
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getStatusEnumAttribute(): SubscriptionStatus
    {
        return SubscriptionStatus::from($this->stripe_status);
    }

    public function isActive(): bool
    {
        return $this->status_enum->isActive();
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function daysUntilTrialEnds(): ?int
    {
        return $this->onTrial()
            ? now()->diffInDays($this->trial_ends_at, false)
            : null;
    }

    public function scopeActive($query)
    {
        return $query->whereIn("stripe_status", ["active", "trialing"]);
    }
}
