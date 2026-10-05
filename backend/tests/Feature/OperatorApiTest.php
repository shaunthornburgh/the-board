<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\InviteRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function operator(): User
    {
        return User::where('email', 'operator@theboard.test')->firstOrFail();
    }

    private function member(): User
    {
        return User::where('email', 'member@theboard.test')->firstOrFail();
    }

    public function test_members_and_guests_cannot_reach_the_operator_api(): void
    {
        $this->getJson('/api/operator/deals')->assertUnauthorized();
        $this->actingAs($this->member(), 'sanctum')->getJson('/api/operator/deals')->assertForbidden();
        $this->actingAs($this->member(), 'sanctum')->getJson('/api/operator/members')->assertForbidden();
        $this->actingAs($this->member(), 'sanctum')->postJson('/api/operator/deals/1/unpublish')->assertForbidden();
    }

    public function test_the_operator_lists_drafts_and_published_deals_with_the_verdict(): void
    {
        $deals = $this->actingAs($this->operator(), 'sanctum')->getJson('/api/operator/deals')->assertOk()->json('deals');

        $this->assertCount(Deal::count(), $deals);
        $this->assertSame('draft', $deals[0]['status']);
        $this->assertArrayHasKey('verdict', $deals[0]);
    }

    public function test_publish_and_unpublish_control_what_members_see(): void
    {
        $draft = Deal::where('status', Deal::STATUS_DRAFT)->first();
        $member = $this->member();

        $this->actingAs($member, 'sanctum')->getJson("/api/deals/{$draft->id}")->assertNotFound();

        $this->actingAs($this->operator(), 'sanctum')->postJson("/api/operator/deals/{$draft->id}/publish")->assertOk()->assertJsonPath('deal.status', 'published');
        $this->actingAs($member, 'sanctum')->getJson("/api/deals/{$draft->id}")->assertOk();

        $this->actingAs($this->operator(), 'sanctum')->postJson("/api/operator/deals/{$draft->id}/unpublish")->assertOk()->assertJsonPath('deal.status', 'draft');
        $this->actingAs($member, 'sanctum')->getJson("/api/deals/{$draft->id}")->assertNotFound();
        $this->actingAs($member, 'sanctum')->get("/api/deals/{$draft->id}/pack")->assertNotFound();
    }

    public function test_the_operator_edits_a_deal_including_teaser_and_verdict(): void
    {
        $deal = Deal::first();

        $this->actingAs($this->operator(), 'sanctum')->putJson("/api/operator/deals/{$deal->id}", [
            'address' => $deal->address,
            'teaser' => 'A new teaser line.',
            'verdict' => 'mention',
            'evidence_json' => '{"Flood zone": "Zone 1"}',
            'comps_json' => '',
        ])->assertOk()->assertJsonPath('deal.teaser', 'A new teaser line.')->assertJsonPath('deal.verdict', 'mention');

        $this->actingAs($this->operator(), 'sanctum')->putJson("/api/operator/deals/{$deal->id}", ['comps_json' => 'not json'])
            ->assertJsonValidationErrors('comps_json');
    }

    public function test_a_pasted_url_opens_an_empty_draft(): void
    {
        $this->actingAs($this->operator(), 'sanctum')->postJson('/api/operator/deals', ['source_url' => 'https://www.example.com/listing/9'])
            ->assertCreated()
            ->assertJsonPath('deal.status', 'draft')
            ->assertJsonPath('deal.source_name', 'example.com');
    }

    public function test_members_screen_invites_activates_revokes_and_shows_requests(): void
    {
        InviteRequest::create(['name' => 'Keen Person', 'email' => 'keen@example.com']);

        $index = $this->actingAs($this->operator(), 'sanctum')->getJson('/api/operator/members')->assertOk();
        $index->assertJsonPath('requests.0.email', 'keen@example.com')->assertJsonPath('room_cap', 20);

        $invited = $this->actingAs($this->operator(), 'sanctum')->postJson('/api/operator/members', ['name' => 'New Member', 'email' => 'new@example.com'])
            ->assertCreated()
            ->json('member');
        $this->assertStringContainsString('/invite/', $invited['invite_link']);

        $this->actingAs($this->operator(), 'sanctum')->postJson("/api/operator/members/{$invited['id']}/active")->assertOk()->assertJsonPath('member.status', 'active');
        $this->actingAs($this->operator(), 'sanctum')->postJson("/api/operator/members/{$invited['id']}/revoke")->assertOk()->assertJsonPath('member.status', 'revoked');
    }

    public function test_the_room_cap_still_applies(): void
    {
        config(['membership.room_cap' => 1]);

        $this->actingAs($this->operator(), 'sanctum')->postJson('/api/operator/members', ['name' => 'Too Many', 'email' => 'many@example.com'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The room is full. Revoke a member before inviting another.');
    }

    public function test_laravel_serves_no_pages(): void
    {
        $this->get('/')->assertRedirect(config('app.frontend_url'));

        foreach (['/login', '/board', '/account', '/settings', '/invite/abc', '/operator/deals', '/deals/1', '/deals/1/pack', '/spa/me'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_the_bot_endpoint_is_gone(): void
    {
        $this->postJson('/api/deals', ['address' => '1 Example Street'], ['Authorization' => 'Bearer demo-bot-token'])
            ->assertNotFound();
        $this->actingAs($this->operator(), 'sanctum')->postJson('/api/deals', ['address' => 'x'])->assertNotFound();
    }
}
