<?php

namespace App\Models\Concerns;

use App\Enums\UserRole;
use App\Models\Auth\Subscription;
use App\Models\Auth\SubscriptionPlan;

trait HasSubscription
{
    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)->active()->latest();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription !== null;
    }

    public function isSubscribedTo(string $planSlug): bool
    {
        $subscription = $this->activeSubscription;
        return $subscription && $subscription->plan->slug === $planSlug;
    }

    public function getSubscriptionPlan(): ?SubscriptionPlan
    {
        return $this->activeSubscription?->plan;
    }

    public function upgradeRole(UserRole $newRole): void
    {
        $this->update(["role" => $newRole]);
    }

    public function downgradeToFree(): void
    {
        $this->update(["role" => UserRole::FREE]);
    }

    public function canUpgradeTo(string $planSlug): bool
    {
        $currentPlan = $this->getSubscriptionPlan();
        $newPlan = SubscriptionPlan::where("slug", $planSlug)->first();

        if (!$newPlan) {
            return false;
        }
        if (!$currentPlan) {
            return true;
        } // Free user can upgrade to anything

        return $newPlan->sort_order > $currentPlan->sort_order;
    }

    public function getSubscriptionDaysRemaining(): ?int
    {
        $subscription = $this->activeSubscription;

        if (!$subscription) {
            return null;
        }

        if ($subscription->onTrial()) {
            return $subscription->daysUntilTrialEnds();
        }

        if ($subscription->ends_at) {
            return max(0, now()->diffInDays($subscription->ends_at, false));
        }

        return null; // Active recurring subscription
    }

    public function getTrialDaysRemaining(): ?int
    {
        $subscription = $this->activeSubscription;
        return $subscription?->daysUntilTrialEnds();
    }
}
