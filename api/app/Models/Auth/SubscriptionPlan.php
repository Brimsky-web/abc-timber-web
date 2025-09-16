<?php
// app/Models/SubscriptionPlan.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        "name",
        "slug",
        "description",
        "price",
        "yearly_price",
        "stripe_price_id",
        "stripe_yearly_price_id",
        "features",
        "api_limit",
        "storage_limit",
        "timber_listings_limit",
        "services_limit",
        "company_profile",
        "api_access",
        "priority_support",
        "is_active",
        "sort_order",
    ];

    protected $casts = [
        "price" => "decimal:2",
        "yearly_price" => "decimal:2",
        "features" => "array",
        "api_limit" => "integer",
        "storage_limit" => "integer",
        "timber_listings_limit" => "integer",
        "services_limit" => "integer",
        "company_profile" => "boolean",
        "api_access" => "boolean",
        "priority_support" => "boolean",
        "is_active" => "boolean",
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 2);
    }

    public function getFormattedYearlyPriceAttribute(): ?string
    {
        return $this->yearly_price
            ? '$' . number_format($this->yearly_price, 2)
            : null;
    }

    public function getYearlySavingsAttribute(): ?string
    {
        if (!$this->yearly_price) {
            return null;
        }

        $monthlyTotal = $this->price * 12;
        $savings = $monthlyTotal - $this->yearly_price;
        $percentage = round(($savings / $monthlyTotal) * 100);

        return "Save {$percentage}% ($" . number_format($savings, 2) . "/year)";
    }

    public function scopeActive($query)
    {
        return $query->where("is_active", true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy("sort_order")->orderBy("price");
    }
}
