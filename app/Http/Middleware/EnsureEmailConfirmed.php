<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Customers who haven't confirmed their e-mail are sent to the confirmation screen (web) or get 403 email_unverified (app). */
class EnsureEmailConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && method_exists($user, 'needsEmailVerification') && $user->needsEmailVerification()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'code' => 'email_unverified',
                    'message' => 'Please confirm your e-mail address first. We sent a 6-digit code to ' . $user->email . '.'], 403);
            }

            return redirect()->guest(route('verification.notice'));
        }

        return $next($request);
    }
}
