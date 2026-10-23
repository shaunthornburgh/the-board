<?php

namespace Tests\Feature;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicFaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function member(): User
    {
        return User::where('email', 'member@theboard.test')->firstOrFail();
    }

    private function operator(): User
    {
        return User::where('email', 'operator@theboard.test')->firstOrFail();
    }

    public function test_the_index_shows_published_teasers_only_with_no_appraisal_detail(): void
    {
        $response = $this->getJson('/api/teasers')->assertOk();

        $deals = $response->json('deals');
        $this->assertCount(Deal::published()->count(), $deals);
        $this->assertSame(['id', 'is_sample', 'photo_url', 'town', 'county', 'asking_price', 'teaser', 'published_at'], array_keys($deals[0]));
    }

    public function test_every_published_sample_has_its_own_local_photo(): void
    {
        $photos = Deal::published()->pluck('photo_url');

        $this->assertSame($photos->count(), $photos->unique()->count());
        foreach ($photos as $photo) {
            $this->assertFileExists(public_path($photo));
            $this->assertStringStartsWith('/images/deals/', $photo);
        }
    }

    public function test_a_deal_needs_a_login(): void
    {
        $deal = Deal::published()->first();

        $this->getJson("/api/deals/{$deal->id}")->assertUnauthorized();
        $this->getJson("/api/deals/{$deal->id}/pack")->assertUnauthorized();
    }

    public function test_a_member_sees_the_full_appraisal_and_pack(): void
    {
        $deal = Deal::published()->whereNotNull('pack_generated_at')->first();

        $this->actingAs($this->member(), 'sanctum')
            ->getJson("/api/deals/{$deal->id}")
            ->assertOk()
            ->assertJsonPath('deal.address', $deal->address)
            ->assertJsonPath('deal.tenant', $deal->tenant)
            ->assertJsonPath('deal.has_pack', true);

        $this->actingAs($this->member(), 'sanctum')->get("/api/deals/{$deal->id}/pack")->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_drafts_stay_operator_only(): void
    {
        $draft = Deal::where('status', DealStatus::AwaitingReview)->first();

        $this->actingAs($this->member(), 'sanctum')->getJson("/api/deals/{$draft->id}")->assertNotFound();
        $this->getJson('/api/teasers')->assertJsonMissing(['id' => $draft->id]);
        $this->actingAs($this->operator(), 'sanctum')->getJson("/api/deals/{$draft->id}")->assertOk();
    }

    public function test_an_invited_member_cannot_open_deals(): void
    {
        $this->member()->update(['membership_status' => User::STATUS_INVITED]);
        $deal = Deal::published()->first();

        $this->actingAs($this->member(), 'sanctum')->getJson("/api/deals/{$deal->id}")->assertForbidden();
    }

    public function test_login_returns_the_user_as_json(): void
    {
        $this->postJson('/api/login', ['email' => 'member@theboard.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.can_see_board', true);

        $this->postJson('/api/login', ['email' => 'member@theboard.test', 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_a_deal_cannot_be_published_without_a_photo(): void
    {
        Storage::fake('photos');
        $deal = Deal::create([
            'development_type_id' => 1,
            'status' => DealStatus::AwaitingReview,
            'address' => '1 Test Street',
            'verdict' => 'send',
            'photo_url' => 'https://example.com/remote.jpg',
        ]);

        $this->actingAs($this->operator(), 'sanctum')->postJson("/api/operator/deals/{$deal->id}/publish")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Upload a photo of the property before publishing.');
        $this->assertFalse($deal->fresh()->isPublished());

        $this->actingAs($this->operator(), 'sanctum')
            ->post("/api/operator/deals/{$deal->id}/photo", ['photo' => new UploadedFile(public_path('images/deals/sample-01.jpg'), 'front.jpg', 'image/jpeg', null, true)])
            ->assertOk()
            ->assertJsonPath('deal.has_photo', true);

        $photo = $deal->fresh()->photo_url;
        $this->assertStringStartsWith('/api/photos/deal-'.$deal->id.'-', $photo);
        Storage::disk('photos')->assertExists(basename($photo));

        $this->actingAs($this->operator(), 'sanctum')->postJson("/api/operator/deals/{$deal->id}/publish")->assertOk();
        $this->assertTrue($deal->fresh()->isPublished());
        $this->get($photo)->assertOk();
    }

    public function test_members_never_receive_the_verdict(): void
    {
        $deal = Deal::published()->whereNotNull('verdict')->first();

        $board = $this->actingAs($this->member(), 'sanctum')->getJson('/api/board?verdict=send')->assertOk();
        $this->assertCount(Deal::published()->count(), $board->json('deals'), 'the verdict filter is gone');
        $board->assertJsonMissingPath('deals.0.verdict')->assertJsonMissingPath('verdicts');

        $this->actingAs($this->member(), 'sanctum')->getJson("/api/deals/{$deal->id}")
            ->assertJsonMissingPath('deal.verdict')
            ->assertJsonMissingPath('deal.verdict_label');

        foreach (Deal::published()->get() as $published) {
            $this->assertDoesNotMatchRegularExpression('/\b(send|mention)\b/i', $published->teaserLine().' '.$published->bot_note);
        }
    }

    public function test_a_deal_with_an_address_and_photo_publishes_without_a_verdict(): void
    {
        $deal = Deal::create([
            'development_type_id' => 1,
            'status' => DealStatus::AwaitingReview,
            'address' => '2 Test Street',
            'photo_url' => '/images/deals/sample-01.jpg',
        ]);

        $this->actingAs($this->operator(), 'sanctum')->postJson("/api/operator/deals/{$deal->id}/publish")->assertOk();

        $this->assertTrue($deal->fresh()->isPublished());
        $this->assertNull($deal->fresh()->verdict);
    }

    public function test_anyone_can_request_an_invite(): void
    {
        $this->postJson('/api/invite-requests', ['name' => 'A Person', 'email' => 'a@example.com'])->assertCreated();
        $this->postJson('/api/invite-requests', ['name' => 'A Person', 'email' => 'nope'])->assertJsonValidationErrors('email');

        $this->assertDatabaseHas('invite_requests', ['email' => 'a@example.com']);
    }
}
