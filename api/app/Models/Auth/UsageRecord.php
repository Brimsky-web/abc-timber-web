<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        "user_id",
        "type",
        "quantity",
        "metadata",
        "recorded_at",
    ];

    protected $casts = [
        "quantity" => "integer",
        "metadata" => "array",
        "recorded_at" => "datetime",
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where("type", $type);
    }

    public function scopeToday($query)
    {
        return $query->whereDate("recorded_at", today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween("recorded_at", [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ]);
    }
}
