<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Who the Bearer token belongs to (null without one), plus the public membership terms. Open to everyone. */
class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'user' => self::present($request->user('sanctum')),
            'membership' => [
                'price_per_month' => config('membership.price_per_month'),
                'minimum_term_months' => config('membership.minimum_term_months'),
                'room_cap' => config('membership.room_cap'),
                'places_taken' => User::holdingPlace()->count(),
            ],
        ]);
    }

    public static function present(?User $user): ?array
    {
        return $user ? [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'membership_status' => $user->membership_status,
            'can_see_board' => $user->canSeeBoard(),
        ] : null;
    }
}
