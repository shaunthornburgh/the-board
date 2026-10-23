<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MeController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/** Token login for the Nuxt site. Each login issues a Sanctum personal access token; logout revokes it. */
class LoginController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Revoked members and scout service accounts cannot sign in; invited members can, but only reach their settings page.
        if (! $user || ! Hash::check($credentials['password'], $user->password) || $user->isRevoked() || $user->isService()) {
            throw ValidationException::withMessages([
                'email' => 'Those details do not match an account.',
            ]);
        }

        return response()->json([
            'token' => $user->createToken('site', ['*'], now()->addMinutes(config('sanctum.site_token_minutes')))->plainTextToken,
            'user' => MeController::present($user),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['user' => null]);
    }
}
