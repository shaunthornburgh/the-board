<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\ScoutSeenListing;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScoutApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    private function scoutToken(string $name = 'rightmove-scout'): string
    {
        Artisan::call('board:scout-token', ['name' => $name]);

        return trim(collect(explode("\n", trim(Artisan::output())))->last());
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    private function candidate(array $overrides = []): array
    {
        return [
            'source' => 'Rightmove',
            'source_key' => '150001',
            'source_url' => 'https://www.rightmove.co.uk/properties/150001',
            'address' => '1 Scout Street',
            'town' => 'Ashford',
            'county' => 'Kent',
            'asking_price' => 500000,
            'tenure' => 'freehold',
            'strategies' => ['PD', 'potential hmo'],
            'notes' => 'Vacant upper floors.',
            ...$overrides,
        ];
    }

    public function test_only_a_scout_token_can_reach_the_scout_api(): void
    {
        $this->postJson('/api/scout/candidates', ['candidates' => [$this->candidate()]])->assertUnauthorized();

        // An operator's login token has every ability, so the role check is what keeps it out.
        $operator = $this->postJson('/api/login', ['email' => 'operator@theboard.test', 'password' => 'password'])->json('token');
        $this->postJson('/api/scout/candidates', ['candidates' => [$this->candidate()]], $this->bearer($operator))->assertForbidden();
        $this->app['auth']->forgetGuards();

        $member = User::where('email', 'member@theboard.test')->first();
        $this->actingAs($member, 'sanctum')->postJson('/api/scout/seen', ['listings' => [['source' => 'x', 'source_key' => '1']]])->assertForbidden();

        // A service account without the scout ability is refused too.
        $token = $this->scoutToken();
        $service = User::where('role', User::ROLE_SERVICE)->first();
        $plain = $service->createToken('other', ['something-else'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/scout/candidates', ['candidates' => [$this->candidate()]], $this->bearer($plain))->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/scout/candidates', ['candidates' => [$this->candidate()]], $this->bearer($token))->assertCreated();
    }

    public function test_scout_accounts_cannot_sign_in_or_see_the_board(): void
    {
        $token = $this->scoutToken();

        $this->getJson('/api/board', $this->bearer($token))->assertForbidden();
        $this->getJson('/api/operator/deals', $this->bearer($token))->assertForbidden();
        $this->assertTrue(User::where('role', User::ROLE_SERVICE)->first()->tokens()->first()->expires_at === null, 'scout tokens do not expire by default');
    }

    public function test_a_pushed_candidate_becomes_a_new_deal_with_its_strategies(): void
    {
        $token = $this->scoutToken();

        $response = $this->postJson('/api/scout/candidates', ['candidates' => [$this->candidate()]], $this->bearer($token))
            ->assertCreated()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('results.0.result', 'created')
            ->assertJsonPath('results.0.status', 'new');

        $deal = Deal::findOrFail($response->json('results.0.deal_id'));
        $this->assertSame(DealStatus::New, $deal->status);
        $this->assertSame('scout', $deal->origin);
        $this->assertSame('rightmove-scout', $deal->scout);
        $this->assertSame('rightmove', $deal->source);
        $this->assertSame('rightmove.co.uk', $deal->source_name);
        $this->assertSame(['PD', 'Potential HMO'], $deal->strategies->pluck('label')->sort()->values()->all());
        $this->assertSame('new', $deal->statusEvents()->first()->to->value);
        $this->assertDatabaseHas('scout_seen_listings', ['source' => 'rightmove', 'source_key' => '150001', 'deal_id' => $deal->id]);

        // Members never see it.
        $member = User::where('email', 'member@theboard.test')->first();
        $this->actingAs($member, 'sanctum')->getJson("/api/deals/{$deal->id}")->assertNotFound();
    }

    public function test_the_same_listing_is_not_created_twice_and_bad_rows_do_not_sink_the_batch(): void
    {
        $token = $this->scoutToken();
        $this->postJson('/api/scout/candidates', ['candidates' => [$this->candidate()]], $this->bearer($token))->assertCreated();

        $response = $this->postJson('/api/scout/candidates', ['candidates' => [
            $this->candidate(['source' => 'rightmove']),
            $this->candidate(['source_key' => '150002', 'strategies' => ['Castle']]),
            $this->candidate(['source_key' => '150003', 'strategies' => ['HMO']]),
            ['source' => 'rightmove'],
        ]], $this->bearer($token));

        $response->assertCreated()
            ->assertJsonPath('created', 1)
            ->assertJsonPath('duplicates', 1)
            ->assertJsonPath('invalid', 2)
            ->assertJsonPath('results.0.result', 'duplicate')
            ->assertJsonPath('results.1.result', 'invalid')
            ->assertJsonPath('results.2.result', 'created')
            ->assertJsonPath('results.3.result', 'invalid');

        $this->assertStringContainsString('Unknown strategy: Castle', $response->json('results.1.errors.strategies.0'));
        $this->assertSame(1, Deal::where('source_key', '150001')->count());
    }

    public function test_scouts_mark_and_check_seen_listings(): void
    {
        $token = $this->scoutToken('zoopla-scout');

        $this->postJson('/api/scout/seen', ['listings' => [
            ['source' => 'Zoopla', 'source_key' => 'z-1', 'source_url' => 'https://www.zoopla.co.uk/z-1'],
            ['source' => 'zoopla', 'source_key' => 'z-2'],
        ]], $this->bearer($token))->assertOk()->assertJsonPath('marked', 2);

        // Marking again only bumps last_seen_at.
        $this->travel(1)->hours();
        $this->postJson('/api/scout/seen', ['listings' => [['source' => 'zoopla', 'source_key' => 'z-1']]], $this->bearer($token))->assertOk();
        $this->assertSame(1, ScoutSeenListing::where('source_key', 'z-1')->count());
        $z1 = ScoutSeenListing::where('source_key', 'z-1')->first();
        $this->assertTrue($z1->last_seen_at->gt($z1->first_seen_at));
        $this->assertSame('https://www.zoopla.co.uk/z-1', $z1->source_url);

        $seen = $this->postJson('/api/scout/seen/check', ['listings' => [
            ['source' => 'zoopla', 'source_key' => 'z-1'],
            ['source' => 'zoopla', 'source_key' => 'z-3'],
            ['source' => 'rightmove', 'source_key' => 'z-2'],
        ]], $this->bearer($token))->assertOk()->json('seen');

        $this->assertSame(['z-1'], array_column($seen, 'source_key'));
        $this->assertSame('zoopla-scout', $seen[0]['scout']);
    }

    public function test_a_revoked_scout_token_stops_working(): void
    {
        $token = $this->scoutToken();
        Artisan::call('board:scout-token', ['name' => 'rightmove-scout', '--revoke' => true]);

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/scout/seen', ['listings' => [['source' => 'x', 'source_key' => '1']]], $this->bearer($token))->assertUnauthorized();
    }
}
