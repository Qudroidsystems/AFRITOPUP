<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\SocialLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Website "Continue with Google / Apple". The page gets an ID token from the
 * provider's own button (Google Identity Services, Sign in with Apple JS) and
 * posts it here; the server verifies it and signs the person in.
 */
class SocialLoginController extends Controller
{
    public function __invoke(Request $request, SocialLogin $social, string $provider): JsonResponse
    {
        $data = $request->validate(['id_token' => 'required|string|max:8000', 'name' => 'nullable|string|max:255']);

        try {
            [$user, $created] = $social->resolve($provider, $data['id_token'], $data['name'] ?? null);
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json(['success' => true, 'created' => $created, 'redirect' => redirect()->intended(route('dashboard'))->getTargetUrl()]);
    }
}
