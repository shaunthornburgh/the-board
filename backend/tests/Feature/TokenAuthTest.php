<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/login', ['email' => $email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['name', 'email', 'role']])
            ->json('token');
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_login_returns_a_token_and_sets_no_cookie(): void
    {
        $response = $this->postJson('/api/login', ['email' => 'member@theboard.test', 'password' => 'password']);

        $response->assertOk();
        $this->assertNotEmpty($response->json('token'));
        $this->assertEmpty($response->headers->getCookies(), 'the API must not set a session or CSRF cookie');
    }

    public function test_bad_credentials_and_revoked_members_get_no_token(): void
    {
        $this->postJson('/api/login', ['email' => 'member@theboard.test', 'password' => 'wrong'])->assertJsonValidationErrors('email');

        User::where('email', 'member@theboard.test')->update(['membership_status' => User::STATUS_REVOKED]);
        $this->postJson('/api/login', ['email' => 'member@theboard.test', 'password' => 'password'])->assertJsonValidationErrors('email');
    }

    public function test_me_reads_the_bearer_token(): void
    {
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user', null)->assertJsonPath('membership.room_cap', 20);

        $token = $this->login('member@theboard.test');
        $this->getJson('/api/me', $this->bearer($token))->assertJsonPath('user.email', 'member@theboard.test');
    }

    public function test_a_member_token_opens_a_deal_but_not_the_operator_api(): void
    {
        $token = $this->login('member@theboard.test');
        $deal = Deal::published()->first();

        $this->getJson("/api/deals/{$deal->id}")->assertUnauthorized();
        $this->getJson("/api/deals/{$deal->id}", $this->bearer($token))->assertOk()->assertJsonPath('deal.id', $deal->id);
        $this->getJson('/api/operator/deals', $this->bearer($token))->assertForbidden();
    }

    public function test_an_operator_token_publishes_without_any_cookie(): void
    {
        $token = $this->login('operator@theboard.test');
        $draft = Deal::where('status', DealStatus::AwaitingReview)->first();

        $response = $this->postJson("/api/operator/deals/{$draft->id}/publish", [], $this->bearer($token));

        $response->assertOk()->assertJsonPath('deal.status', 'published');
        $this->assertEmpty($response->headers->getCookies());
    }

    public function test_logout_revokes_the_token(): void
    {
        $token = $this->login('member@theboard.test');

        $this->postJson('/api/logout', [], $this->bearer($token))->assertOk();
        $this->assertSame(0, User::where('email', 'member@theboard.test')->first()->tokens()->count());

        // Forget the user the guard resolved before logout, as a new request would.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/board', $this->bearer($token))->assertUnauthorized();
    }

    public function test_revoking_a_member_kills_their_tokens(): void
    {
        $memberToken = $this->login('member@theboard.test');
        $operatorToken = $this->login('operator@theboard.test');
        $member = User::where('email', 'member@theboard.test')->first();

        $this->postJson("/api/operator/members/{$member->id}/revoke", [], $this->bearer($operatorToken))->assertOk();

        $this->assertSame(0, $member->tokens()->count());
    }

    public function test_accepting_an_invite_returns_a_token(): void
    {
        $operatorToken = $this->login('operator@theboard.test');
        $link = $this->postJson('/api/operator/members', ['name' => 'New Person', 'email' => 'new@example.com'], $this->bearer($operatorToken))
            ->assertCreated()
            ->json('member.invite_link');

        $this->assertStringStartsWith(config('app.frontend_url').'/invite/', $link);
        $token = basename($link);

        $this->getJson("/api/invite/{$token}")->assertOk()->assertJsonPath('email', 'new@example.com');
        $this->postJson("/api/invite/{$token}", ['password' => 'a-long-password', 'password_confirmation' => 'a-long-password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }
}
