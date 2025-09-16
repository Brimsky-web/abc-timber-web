<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAbilities
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$abilities,
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

        // Check if user has valid token
        if (!$user->hasValidToken()) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Invalid or expired token",
                ],
                401,
            );
        }

        // Check abilities
        foreach ($abilities as $ability) {
            if (!$user->tokenCan($ability)) {
                return response()->json(
                    [
                        "success" => false,
                        "message" => "Insufficient permissions",
                        "required_abilities" => $abilities,
                        "user_abilities" => $user->getRoleAbilities(),
                    ],
                    403,
                );
            }
        }

        return $next($request);
    }
}
