<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitByRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Check if user has valid Sanctum token
        if (!$user->hasValidToken()) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Invalid or expired token",
                ],
                401,
            );
        }

        $limit = $user->role->getRateLimit();

        // Skip rate limiting for unlimited plans
        if ($limit === 0) {
            return $next($request);
        }

        // Check if user has exceeded API limit
        if ($user->hasExceededApiLimit()) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "API rate limit exceeded",
                    "limit" => $limit,
                    "current_usage" => $user->api_calls_this_hour,
                    "reset_at" => $user->api_calls_reset_at,
                    "upgrade_available" => $user->role->value !== "enterprise",
                ],
                429,
            );
        }

        // Track API usage
        $user->trackUsage("api_call", 1, [
            "endpoint" => $request->path(),
            "method" => $request->method(),
            "ip" => $request->ip(),
            "user_agent" => $request->userAgent(),
        ]);

        return $next($request);
    }
}
