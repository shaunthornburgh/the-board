<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\InviteRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** JSON for the operator's members screen in Nuxt. Nothing is emailed; the operator sends the invite link. */
class MemberController extends Controller
{
    public function index(): JsonResponse
    {
        $members = User::members()
            ->orderByRaw("case membership_status when 'active' then 0 when 'invited' then 1 else 2 end")
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->present($user));

        return response()->json([
            'members' => $members,
            'requests' => InviteRequest::latest()->limit(50)->get()->map(fn (InviteRequest $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'email' => $r->email,
                'phone' => $r->phone,
                'message' => $r->message,
                'created_at' => $r->created_at->toIso8601String(),
            ]),
            'places_taken' => User::holdingPlace()->count(),
            'room_cap' => config('membership.room_cap'),
            'price_per_month' => config('membership.price_per_month'),
            'minimum_term_months' => config('membership.minimum_term_months'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ]);

        if ($this->roomIsFull()) {
            return response()->json(['message' => 'The room is full. Revoke a member before inviting another.'], 422);
        }

        $user = User::create([
            ...$validated,
            'role' => User::ROLE_MEMBER,
            'membership_status' => User::STATUS_INVITED,
            'invite_token' => Str::random(48),
            'invited_at' => now(),
        ]);

        return response()->json([
            'message' => "Invited {$user->name}. Send them the link below.",
            'member' => $this->present($user),
        ], 201);
    }

    public function markInvited(User $user): JsonResponse
    {
        $this->ensureMember($user);

        if ($user->isRevoked() && $this->roomIsFull()) {
            return response()->json(['message' => 'The room is full.'], 422);
        }

        $user->update([
            'membership_status' => User::STATUS_INVITED,
            'invite_token' => $user->password ? null : ($user->invite_token ?? Str::random(48)),
            'invited_at' => $user->invited_at ?? now(),
            'activated_at' => null,
            'revoked_at' => null,
        ]);

        return $this->done($user, "{$user->name} is marked invited.");
    }

    public function markActive(User $user): JsonResponse
    {
        $this->ensureMember($user);

        if ($user->isRevoked() && $this->roomIsFull()) {
            return response()->json(['message' => 'The room is full.'], 422);
        }

        if ($user->membership_status === User::STATUS_ACTIVE) {
            return $this->done($user, "{$user->name} is already active.");
        }

        $user->update([
            'membership_status' => User::STATUS_ACTIVE,
            'activated_at' => now(),
            'revoked_at' => null,
        ]);

        return $this->done($user, "{$user->name} is active. Their three-month minimum starts today.");
    }

    public function revoke(User $user): JsonResponse
    {
        $this->ensureMember($user);

        $user->update([
            'membership_status' => User::STATUS_REVOKED,
            'invite_token' => null,
            'revoked_at' => now(),
        ]);

        // Revoke their tokens so the revocation takes effect immediately.
        $user->tokens()->delete();

        return $this->done($user, "{$user->name} has been revoked.");
    }

    private function done(User $user, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'member' => $this->present($user)]);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->membership_status,
            'invited_at' => $user->invited_at?->toDateString(),
            'activated_at' => $user->activated_at?->toDateString(),
            'minimum_term_ends_at' => $user->minimumTermEndsAt()?->toDateString(),
            'revoked_at' => $user->revoked_at?->toDateString(),
            'invite_link' => $user->invite_token ? config('app.frontend_url').'/invite/'.$user->invite_token : null,
        ];
    }

    private function roomIsFull(): bool
    {
        return User::holdingPlace()->count() >= config('membership.room_cap');
    }

    private function ensureMember(User $user): void
    {
        abort_unless($user->role === User::ROLE_MEMBER, 404);
    }
}
