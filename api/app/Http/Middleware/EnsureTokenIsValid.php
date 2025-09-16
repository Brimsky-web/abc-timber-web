<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Unauthorized - No valid token provided",
                ],
                401,
            );
        }

        $token = $user->currentAccessToken();

        if (!$token) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "No active token found",
                ],
                401,
            );
        }

        // Check if token is expired
        if ($token->expires_at && $token->expires_at->isPast()) {
            // Delete expired token
            $token->delete();

            return response()->json(
                [
                    "success" => false,
                    "message" => "Token has expired",
                    "expired_at" => $token->expires_at,
                ],
                401,
            );
        }

        // Update last used timestamp
        $token->forceFill(["last_used_at" => now()])->save();

        return $next($request);
    }
}
