<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'membership_status', 'invite_token', 'invited_at', 'activated_at', 'revoked_at'])]
#[Hidden(['password', 'remember_token', 'invite_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_OPERATOR = 'operator';

    public const ROLE_MEMBER = 'member';

    public const STATUS_INVITED = 'invited';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invited_at' => 'datetime',
            'activated_at' => 'datetime',
            'revoked_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isOperator(): bool
    {
        return $this->role === self::ROLE_OPERATOR;
    }

    public function isActiveMember(): bool
    {
        return $this->role === self::ROLE_MEMBER && $this->membership_status === self::STATUS_ACTIVE;
    }

    public function isRevoked(): bool
    {
        return $this->membership_status === self::STATUS_REVOKED;
    }

    /** Operators and active members can see the board. */
    public function canSeeBoard(): bool
    {
        return $this->isOperator() || $this->isActiveMember();
    }

    public function minimumTermEndsAt(): ?Carbon
    {
        return $this->activated_at?->copy()->addMonthsNoOverflow(config('membership.minimum_term_months'));
    }

    public function scopeMembers(Builder $query): void
    {
        $query->where('role', self::ROLE_MEMBER);
    }

    /** Invited and active members both hold a place in the room. */
    public function scopeHoldingPlace(Builder $query): void
    {
        $query->members()->whereIn('membership_status', [self::STATUS_INVITED, self::STATUS_ACTIVE]);
    }
}
