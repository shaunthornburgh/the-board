<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'membership' => [
                'price_per_month' => config('membership.price_per_month'),
                'minimum_term_months' => config('membership.minimum_term_months'),
                'room_cap' => config('membership.room_cap'),
                'places_taken' => User::holdingPlace()->count(),
            ],
            'account' => [
                'role' => $user->role,
                'status' => $user->membership_status,
                'activated_at' => $user->activated_at?->toDateString(),
                'minimum_term_ends_at' => $user->minimumTermEndsAt()?->toDateString(),
            ],
        ]);
    }
}
