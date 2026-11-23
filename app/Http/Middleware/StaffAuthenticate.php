<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('staff')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            return redirect()->route('login');
        }

        /** @var \App\Models\StaffUser $staff */
        $staff = auth('staff')->user();

        if (! $staff->isActive()) {
            auth('staff')->logout();

            return redirect()->route('login')
                ->withErrors(['username' => 'Your account is not active.']);
        }

        return $next($request);
    }
}
