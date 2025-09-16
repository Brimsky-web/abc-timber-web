<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        "user_id",
        "subscription_id",
        "stripe_id",
        "amount",
        "currency",
        "status",
        "metadata",
        "paid_at",
    ];

    protected $casts = [
        "amount" => "decimal:2",
        "metadata" => "array",
        "paid_at" => "datetime",
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function getFormattedAmountAttribute(): string
    {
        return strtoupper($this->currency) .
            ' $' .
            number_format($this->amount, 2);
    }

    public function isSuccessful(): bool
    {
        return $this->status === "succeeded";
    }

    public function scopeSuccessful($query)
    {
        return $query->where("status", "succeeded");
    }
}
