<?php

namespace Tests\Feature;

use App\Models\Source;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SourceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    private function scoutToken(): string
    {
        Artisan::call('board:scout-token', ['name' => 'auction-scout']);

        return trim(collect(explode("\n", trim(Artisan::output())))->last());
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => "Bearer {$token}"];
    }

    private function operator(): User
    {
        return User::where('email', 'operator@theboard.test')->firstOrFail();
    }

    public function test_only_a_scout_token_can_fetch_sources(): void
    {
        $this->getJson('/api/scout/sources?type=auction')->assertUnauthorized();

        // An operator's login token has every ability, so the role check is what keeps it out.
        $operator = $this->postJson('/api/login', ['email' => 'operator@theboard.test', 'password' => 'password'])->json('token');
        $this->getJson('/api/scout/sources?type=auction', $this->bearer($operator))->assertForbidden();
        $this->app['auth']->forgetGuards();

        $member = User::where('email', 'member@theboard.test')->first();
        $this->actingAs($member, 'sanctum')->getJson('/api/scout/sources?type=auction')->assertForbidden();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/scout/sources?type=auction', $this->bearer($this->scoutToken()))->assertOk();
    }

    public function test_scouts_get_active_sources_of_one_type_by_priority_then_name(): void
    {
        $token = $this->scoutToken();
        Source::where('name', 'Acuitus')->update(['active' => false]);

        $auction = $this->getJson('/api/scout/sources?type=auction', $this->bearer($token))->assertOk();

        $auction->assertJsonPath('data.0', [
            'id' => Source::where('name', 'AuctionRadar')->value('id'),
            'type' => 'auction',
            'priority' => 1,
            'category' => 'Aggregator',
            'name' => 'AuctionRadar',
            'url' => 'https://auctionradar.co.uk/',
            'notes' => 'Hit first',
        ]);
        $auction->assertJsonPath('data.1.name', 'Property Auction Action');
        $auction->assertJsonPath('data.2.name', 'Allsop Commercial');
        $auction->assertJsonPath('data.16.name', 'McHugh and Co');
        $this->assertCount(17, $auction->json('data'), '18 seeded, one inactive');
        $this->assertSame(['auction'], array_values(array_unique(array_column($auction->json('data'), 'type'))));
        $this->assertNotContains('Acuitus', array_column($auction->json('data'), 'name'));

        $portal = $this->getJson('/api/scout/sources?type=portal', $this->bearer($token))->assertOk()->json('data');
        $this->assertCount(7, $portal);
        $this->assertSame(['CommercialUK', 'LoopNet UK', 'NovaLoca', 'Proplist', 'OnTheMarket Commercial', 'Rightmove Commercial', 'Zoopla Commercial'], array_column($portal, 'name'));

        $this->getJson('/api/scout/sources?type=forum', $this->bearer($token))->assertJsonValidationErrors('type');
    }

    public function test_the_operator_manages_sources(): void
    {
        $this->getJson('/api/operator/sources')->assertUnauthorized();
        $this->actingAs(User::where('email', 'member@theboard.test')->first(), 'sanctum')->getJson('/api/operator/sources')->assertForbidden();

        $operator = $this->operator();
        $this->actingAs($operator, 'sanctum')->getJson('/api/operator/sources')->assertOk()->assertJsonCount(25, 'data');

        $id = $this->actingAs($operator, 'sanctum')->postJson('/api/operator/sources', [
            'type' => 'portal', 'priority' => 3, 'category' => 'Local portal', 'name' => 'Example Portal', 'url' => 'https://example.com/',
        ])->assertCreated()->assertJsonPath('data.active', true)->json('data.id');

        $this->actingAs($operator, 'sanctum')->postJson('/api/operator/sources', [
            'type' => 'portal', 'priority' => 3, 'category' => 'Local portal', 'name' => 'Duplicate', 'url' => 'https://example.com/',
        ])->assertJsonValidationErrors('url');

        $this->actingAs($operator, 'sanctum')->putJson("/api/operator/sources/{$id}", ['active' => false, 'notes' => 'Paused'])
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.notes', 'Paused')
            ->assertJsonPath('data.name', 'Example Portal');

        $this->actingAs($operator, 'sanctum')->deleteJson("/api/operator/sources/{$id}")->assertOk();
        $this->assertDatabaseMissing('sources', ['id' => $id]);
    }
}
