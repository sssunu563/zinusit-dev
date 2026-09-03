<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    /**
     * Redirect authenticated users who have must_change_password = true
     * to the change-password page, except for that page itself and logout.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            $allowedRoutes = ['password.change', 'password.change.update', 'logout'];

            if (!in_array($request->route()?->getName(), $allowedRoutes, true)) {
                return redirect()->route('password.change');
            }
        }

        return $next($request);
    }
}
