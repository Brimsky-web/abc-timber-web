<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles,
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

        $userRole = $user->role->value;
        $allowedRoles = array_map(fn($role) => UserRole::from($role), $roles);

        if (!in_array($user->role, $allowedRoles)) {
            return response()->json(
                [
                    "success" => false,
                    "message" => "Insufficient permissions",
                    "required_roles" => array_map(
                        fn($role) => $role->label(),
                        $allowedRoles,
                    ),
                    "current_role" => $user->role->label(),
                ],
                403,
            );
        }

        return $next($request);
    }
}
