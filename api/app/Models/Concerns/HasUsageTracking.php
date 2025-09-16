<?php

namespace App\Models\Concerns;

use App\Models\Auth\UsageRecord;

trait HasUsageTracking
{
    public function trackUsage(
        string $type,
        int $quantity = 1,
        array $metadata = [],
    ): void {
        $this->usageRecords()->create([
            "type" => $type,
            "quantity" => $quantity,
            "metadata" => $metadata,
            "recorded_at" => now(),
        ]);

        // Update counters based on type
        match ($type) {
            "api_call" => $this->incrementApiCalls(),
            "storage" => $this->updateStorageUsage($metadata["bytes"] ?? 0),
            default => null,
        };
    }

    public function getUsageThisMonth(string $type): int
    {
        return $this->usageRecords()
            ->byType($type)
            ->thisMonth()
            ->sum("quantity");
    }

    public function getUsageToday(string $type): int
    {
        return $this->usageRecords()->byType($type)->today()->sum("quantity");
    }

    public function resetApiCalls(): void
    {
        $this->update([
            "api_calls_this_hour" => 0,
            "api_calls_reset_at" => now()->addHour(),
        ]);
    }

    public function incrementApiCalls(): void
    {
        // Reset if hour has passed
        if ($this->api_calls_reset_at && $this->api_calls_reset_at->isPast()) {
            $this->resetApiCalls();
        }

        $this->increment("api_calls_this_hour");
        $this->increment("total_api_calls");
    }

    public function hasExceededApiLimit(): bool
    {
        $limit = $this->role->getRateLimit();

        if ($limit === 0) {
            return false;
        } // Unlimited

        return $this->api_calls_this_hour >= $limit;
    }

    public function updateStorageUsage(int $bytes): void
    {
        $this->increment("storage_used_bytes", $bytes);
    }

    public function hasExceededStorageLimit(): bool
    {
        $limit = $this->role->getStorageLimit();

        if ($limit === 0) {
            return false;
        } // Unlimited

        $limitBytes = $limit * 1024 * 1024; // Convert MB to bytes
        return $this->storage_used_bytes >= $limitBytes;
    }

    public function getStorageUsedFormatted(): string
    {
        $bytes = $this->storage_used_bytes;
        $units = ["B", "KB", "MB", "GB"];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . " " . $units[$i];
    }

    public function getStorageUsagePercentage(): float
    {
        $limit = $this->role->getStorageLimit();

        if ($limit === 0) {
            return 0;
        } // Unlimited

        $limitBytes = $limit * 1024 * 1024;
        return min(100, ($this->storage_used_bytes / $limitBytes) * 100);
    }
}
