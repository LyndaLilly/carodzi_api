<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IsSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Assuming your Admin model has a boolean or role check for superadmin
        if ($user && $user->role === 'superadmin') {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized.'], 403);
    }
}
