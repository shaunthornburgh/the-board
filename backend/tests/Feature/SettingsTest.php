<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function member(array $attributes = []): User
    {
        return User::create([
            'name' => 'Sample Member',
            'email' => 'member@theboard.test',
            'password' => 'password',
            'role' => User::ROLE_MEMBER,
            'membership_status' => User::STATUS_ACTIVE,
            ...$attributes,
        ]);
    }

    public function test_settings_need_a_login(): void
    {
        $this->putJson('/api/settings', ['name' => 'X', 'email' => 'x@example.com'])->assertUnauthorized();
    }

    public function test_a_member_can_change_their_name_without_the_current_password(): void
    {
        $user = $this->member();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/settings', ['name' => 'New Name', 'email' => 'member@theboard.test'])
            ->assertOk()
            ->assertJsonPath('user.name', 'New Name');

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_changing_the_password_needs_the_current_password(): void
    {
        $user = $this->member();
        $body = ['name' => $user->name, 'email' => $user->email, 'password' => 'a-new-password', 'password_confirmation' => 'a-new-password'];

        $this->actingAs($user, 'sanctum')->putJson('/api/settings', $body)->assertJsonValidationErrors('current_password');
        $this->actingAs($user, 'sanctum')->putJson('/api/settings', [...$body, 'current_password' => 'wrong'])->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));

        $this->actingAs($user, 'sanctum')->putJson('/api/settings', [...$body, 'current_password' => 'password'])->assertOk();
        $this->assertTrue(Hash::check('a-new-password', $user->fresh()->password));
    }

    public function test_changing_the_email_needs_the_current_password_and_a_free_address(): void
    {
        $user = $this->member();
        $this->member(['email' => 'taken@example.com', 'name' => 'Other']);

        $this->actingAs($user, 'sanctum')->putJson('/api/settings', ['name' => $user->name, 'email' => 'new@example.com'])
            ->assertJsonValidationErrors('current_password');

        $this->actingAs($user, 'sanctum')->putJson('/api/settings', ['name' => $user->name, 'email' => 'taken@example.com', 'current_password' => 'password'])
            ->assertJsonValidationErrors('email');

        $this->actingAs($user, 'sanctum')->putJson('/api/settings', ['name' => $user->name, 'email' => 'new@example.com', 'current_password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'new@example.com');
    }

    public function test_settings_only_touch_name_email_and_password(): void
    {
        $user = $this->member();

        $this->actingAs($user, 'sanctum')->putJson('/api/settings', [
            'name' => $user->name,
            'email' => $user->email,
            'role' => User::ROLE_OPERATOR,
            'membership_status' => User::STATUS_ACTIVE,
        ])->assertOk();

        $this->assertSame(User::ROLE_MEMBER, $user->fresh()->role);
    }
}
