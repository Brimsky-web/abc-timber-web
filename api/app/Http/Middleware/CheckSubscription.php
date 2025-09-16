<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(
        Request $request,
        Closure $next,
        string $feature = null,
    ): Response {
        $user = $request->user();

        if (!$user) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Unauthorized",
                ],
                401,
            );
        }

        // Ensure valid Sanctum token
        if (!$user->hasValidToken()) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Invalid or expired token",
                ],
                401,
            );
        }

        // Check if user has access to the feature
        if ($feature && !$user->canAccessFeature($feature)) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "This feature requires a paid subscription",
                    "feature" => $feature,
                    "current_plan" => $user->role->label(),
                    "upgrade_required" => true,
                    "available_plans" => $this->getUpgradeOptions($user),
                ],
                402,
            ); // Payment Required
        }

        // Check feature limits
        if ($feature && $user->hasReachedLimit($feature)) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "You have reached the limit for {$feature}",
                    "limit_reached" => true,
                    "current_limit" => $user->getFeatureLimit($feature),
                    "current_usage" => $user->getCurrentUsage($feature),
                    "upgrade_options" => $this->getUpgradeOptions($user),
                ],
                429,
            ); // Too Many Requests
        }

        return $next($request);
    }

    private function getUpgradeOptions($user): array
    {
        $currentRole = $user->role->value;

        return match ($currentRole) {
            "free" => ["basic", "premium", "enterprise"],
            "basic" => ["premium", "enterprise"],
            "premium" => ["enterprise"],
            default => [],
        };
    }
}
