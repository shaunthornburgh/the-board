<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

/** The signed-in user's own name, email and password. Nothing else is editable here. */
class SettingsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            // Changing the password or the sign-in email needs the current password.
            'current_password' => [
                Rule::requiredIf(fn () => $request->filled('password') || $request->input('email') !== $user->email),
                'nullable',
                'current_password',
            ],
            'password' => ['nullable', 'confirmed', Password::min(10)],
        ]);

        $user->fill(['name' => $validated['name'], 'email' => $validated['email']]);

        if (filled($validated['password'] ?? null)) {
            $user->password = $validated['password'];
        }

        $passwordChanged = $user->isDirty('password');
        $user->save();

        // A new password revokes the user's other tokens; the one making this request stays valid.
        if ($passwordChanged) {
            $current = $user->currentAccessToken();

            $user->tokens()
                ->when($current instanceof PersonalAccessToken, fn ($tokens) => $tokens->whereKeyNot($current->getKey()))
                ->delete();
        }

        return response()->json(['user' => MeController::present($user)]);
    }
}
