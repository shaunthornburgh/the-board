<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MeController;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $user = $this->invitee($token);

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    public function store(Request $request, string $token): JsonResponse
    {
        $user = $this->invitee($token);

        $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        $user->forceFill(['password' => $request->input('password'), 'invite_token' => null])->save();

        // Setting the password signs the member in.
        return response()->json([
            'token' => $user->createToken('site')->plainTextToken,
            'user' => MeController::present($user),
        ]);
    }

    private function invitee(string $token): User
    {
        return User::members()
            ->where('invite_token', $token)
            ->where('membership_status', '!=', User::STATUS_REVOKED)
            ->firstOrFail();
    }
}
