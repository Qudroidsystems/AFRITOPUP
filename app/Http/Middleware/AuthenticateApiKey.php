<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;

/**
 * Reseller API: `Authorization: Bearer afk_live_…` (or `X-API-Key`).
 * The key's owner must be active and hold "Use reseller API"; if the key
 * lists IP addresses, the request must come from one of them.
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->bearerToken() ?: $request->header('X-API-Key');
        $key = $plain ? ApiKey::findByPlain($plain) : null;
        $fail = fn (string $msg, string $code, int $status) => response()->json(['success' => false, 'code' => $code, 'message' => $msg], $status);

        if (!$key) return $fail('Missing or invalid API key.', 'unauthenticated', 401);
        if (!$key->allows($request->ip())) return $fail('Requests from this IP address are not allowed for this key.', 'ip_not_allowed', 403);
        $user = $key->user;
        if (!$user || $user->is_disabled) return $fail('This account is disabled.', 'account_disabled', 403);
        if (!$user->can('Use reseller API')) return $fail('This account may not use the API.', 'forbidden', 403);

        if (!$key->last_used_at || $key->last_used_at->lt(now()->subMinute())) $key->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('api_key', $key);
        $request->attributes->set('api_key_id', $key->id);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
