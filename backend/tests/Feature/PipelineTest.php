<?php

namespace Tests\Feature;

use App\Enums\AnalystVerdict;
use App\Enums\DealStatus;
use App\Models\Deal;
use App\Models\User;
use App\Services\Analyst\AnalystClient;
use App\Services\Analyst\AnalystException;
use App\Services\Analyst\AnalystResult;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PipelineTest extends TestCase
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

    private function deal(DealStatus $status, array $attributes = []): Deal
    {
        return Deal::create([
            'development_type_id' => 1,
            'status' => $status,
            'address' => '9 Pipeline Road',
            'photo_url' => '/images/deals/sample-01.jpg',
            ...$attributes,
        ]);
    }

    private function act(string $path, array $body = [])
    {
        return $this->actingAs($this->operator(), 'sanctum')->postJson("/api/operator/deals/{$path}", $body);
    }

    /** The analyst always answers with this verdict. */
    private function analystSays(AnalystVerdict $verdict, string $notes = 'Looks fine.'): void
    {
        $this->app->bind(AnalystClient::class, fn () => new class($verdict, $notes) implements AnalystClient
        {
            public function __construct(private AnalystVerdict $verdict, private string $notes) {}

            public function analyse(Deal $deal): AnalystResult
            {
                return new AnalystResult($this->verdict, $this->notes);
            }
        });
    }

    public function test_the_full_happy_path_from_new_to_published_with_history(): void
    {
        $this->analystSays(AnalystVerdict::Send, 'Strong PD case.');
        $deal = $this->deal(DealStatus::New);

        $this->act("{$deal->id}/send-to-analyst")->assertOk()
            ->assertJsonPath('deal.status', 'analyst_done')
            ->assertJsonPath('deal.analyst_verdict', 'send')
            ->assertJsonPath('deal.analyst_notes', 'Strong PD case.')
            ->assertJsonPath('deal.verdict', 'send')
            ->assertJsonPath('message', 'Analyst verdict: Send.');

        $this->act("{$deal->id}/awaiting-review")->assertOk()->assertJsonPath('deal.status', 'awaiting_review');
        $response = $this->act("{$deal->id}/publish")->assertOk()->assertJsonPath('deal.status', 'published');

        $this->assertSame(
            [['awaiting_review', 'published'], ['analyst_done', 'awaiting_review'], ['awaiting_analyst', 'analyst_done'], ['new', 'awaiting_analyst']],
            array_map(fn ($e) => [$e['from'], $e['to']], $response->json('history')),
        );
        $this->assertSame('Board Operator', $response->json('history.0.by'));
        $this->assertSame('analyst', $response->json('history.2.by'));
    }

    public function test_moves_not_in_the_pipeline_are_refused(): void
    {
        $new = $this->deal(DealStatus::New);
        $this->act("{$new->id}/publish")->assertUnprocessable()->assertJsonPath('message', 'This deal is New, so it cannot move to Published.');
        $this->act("{$new->id}/awaiting-review")->assertUnprocessable(); // no skipping the analyst
        $this->act("{$new->id}/analyst-result", ['verdict' => 'send'])->assertUnprocessable();

        $done = $this->deal(DealStatus::AnalystDone);
        $this->act("{$done->id}/publish")->assertUnprocessable();
        $this->act("{$done->id}/send-to-analyst")->assertUnprocessable();

        $waiting = $this->deal(DealStatus::AwaitingAnalyst);
        $this->act("{$waiting->id}/reject")->assertUnprocessable();

        $rejected = $this->deal(DealStatus::Rejected);
        foreach (['send-to-analyst', 'awaiting-review', 'publish', 'reject'] as $action) {
            $this->act("{$rejected->id}/{$action}")->assertUnprocessable();
        }

        $this->assertSame(DealStatus::New, $new->fresh()->status);
        $this->assertSame(0, $new->statusEvents()->count());
    }

    public function test_the_operator_rejects_from_new_analyst_done_and_awaiting_review(): void
    {
        foreach ([DealStatus::New, DealStatus::AnalystDone, DealStatus::AwaitingReview] as $status) {
            $deal = $this->deal($status);

            $this->act("{$deal->id}/reject", ['reason' => 'Article 4 area'])->assertOk()
                ->assertJsonPath('deal.status', 'rejected')
                ->assertJsonPath('deal.rejection_reason', 'Article 4 area');

            $this->assertNotNull($deal->fresh()->rejected_at);
            $this->assertSame('Article 4 area', $deal->statusEvents()->first()->note);
        }
    }

    public function test_bulk_reject_skips_deals_that_cannot_be_rejected(): void
    {
        $a = $this->deal(DealStatus::New);
        $b = $this->deal(DealStatus::AwaitingReview);
        $c = $this->deal(DealStatus::AwaitingAnalyst);

        $this->act('bulk/reject', ['ids' => [$a->id, $b->id, $c->id], 'reason' => 'Not for us'])->assertOk()
            ->assertJsonPath('done', [$a->id, $b->id])
            ->assertJsonPath("skipped.{$c->id}", 'This deal is Awaiting analyst, so it cannot move to Rejected.')
            ->assertJsonPath('message', 'Rejected 2 deals. Skipped 1: This deal is Awaiting analyst, so it cannot move to Rejected.');

        $this->assertSame(DealStatus::Rejected, $a->fresh()->status);
        $this->assertSame(DealStatus::AwaitingAnalyst, $c->fresh()->status);
    }

    public function test_bulk_send_to_analyst(): void
    {
        $this->analystSays(AnalystVerdict::Mention);
        $a = $this->deal(DealStatus::New);
        $b = $this->deal(DealStatus::New);
        $c = $this->deal(DealStatus::AwaitingReview);

        $this->act('bulk/send-to-analyst', ['ids' => [$a->id, $b->id, $c->id]])->assertOk()->assertJsonPath('done', [$a->id, $b->id]);

        $this->assertSame(DealStatus::AnalystDone, $a->fresh()->status);
        $this->assertSame(AnalystVerdict::Mention, $b->fresh()->analyst_verdict);
        $this->assertSame(DealStatus::AwaitingReview, $c->fresh()->status);
    }

    public function test_a_failed_analyst_call_is_shown_and_can_be_retried(): void
    {
        $this->app->bind(AnalystClient::class, fn () => new class implements AnalystClient
        {
            public function analyse(Deal $deal): AnalystResult
            {
                throw new AnalystException('Grok is down.');
            }
        });
        $deal = $this->deal(DealStatus::New);

        $this->act("{$deal->id}/send-to-analyst")->assertOk()
            ->assertJsonPath('deal.status', 'awaiting_analyst')
            ->assertJsonPath('deal.analyst_error', 'Grok is down.')
            ->assertJsonPath('message', 'Sent to the analyst, but it failed: Grok is down.');

        $this->analystSays(AnalystVerdict::Reject, 'Too small.');
        $this->act("{$deal->id}/send-to-analyst")->assertOk()
            ->assertJsonPath('deal.status', 'analyst_done')
            ->assertJsonPath('deal.analyst_error', null)
            ->assertJsonPath('deal.analyst_verdict', 'reject')
            ->assertJsonPath('deal.verdict', null); // reject is not copied into the member-facing send/mention verdict
    }

    public function test_the_operator_records_an_analyst_result_by_hand_and_kill_means_reject(): void
    {
        $deal = $this->deal(DealStatus::AwaitingAnalyst);

        $this->act("{$deal->id}/analyst-result", ['verdict' => 'maybe'])->assertJsonValidationErrors('verdict');
        $this->act("{$deal->id}/analyst-result", ['verdict' => 'KILL', 'notes' => 'Flood zone 3.'])->assertOk()
            ->assertJsonPath('deal.status', 'analyst_done')
            ->assertJsonPath('deal.analyst_verdict', 'reject')
            ->assertJsonPath('deal.analyst_notes', 'Flood zone 3.')
            ->assertJsonPath('history.0.by', 'Board Operator');
    }

    public function test_the_analyst_does_not_overwrite_what_the_operator_wrote(): void
    {
        $this->analystSays(AnalystVerdict::Send, 'Raw analyst notes, mentions send.');
        $deal = $this->deal(DealStatus::New, ['verdict' => 'mention', 'bot_note' => 'Operator note.']);

        $this->act("{$deal->id}/send-to-analyst")->assertOk();

        $deal->refresh();
        $this->assertSame('mention', $deal->verdict);
        $this->assertSame('Operator note.', $deal->bot_note);
        $this->assertSame('Raw analyst notes, mentions send.', $deal->analyst_notes);
    }

    public function test_the_stub_analyst_runs_by_default(): void
    {
        $deal = $this->deal(DealStatus::New, ['source_key' => 'stub-1', 'source' => 'test']);

        $this->act("{$deal->id}/send-to-analyst")->assertOk()->assertJsonPath('deal.status', 'analyst_done');
        $this->assertStringStartsWith('[stub]', $deal->fresh()->analyst_notes);
    }

    public function test_the_grok_driver_without_config_records_an_error(): void
    {
        config(['services.analyst.driver' => 'grok', 'services.grok.url' => null]);
        $deal = $this->deal(DealStatus::New);

        $this->act("{$deal->id}/send-to-analyst")->assertOk()->assertJsonPath('deal.status', 'awaiting_analyst');
        $this->assertStringContainsString('not configured', $deal->fresh()->analyst_error);
    }

    public function test_analyst_fields_and_strategies_stay_operator_only(): void
    {
        $deal = Deal::published()->first();
        $deal->update(['analyst_verdict' => 'send', 'analyst_notes' => 'Secret.', 'scout_notes' => 'Scout said.']);

        $member = User::where('email', 'member@theboard.test')->first();
        $this->actingAs($member, 'sanctum')->getJson("/api/deals/{$deal->id}")->assertOk()
            ->assertJsonMissingPath('deal.analyst_verdict')
            ->assertJsonMissingPath('deal.analyst_notes')
            ->assertJsonMissingPath('deal.scout_notes')
            ->assertJsonMissingPath('deal.rejection_reason');
    }

    public function test_the_operator_sets_strategy_tags(): void
    {
        $deal = $this->deal(DealStatus::New);

        $this->actingAs($this->operator(), 'sanctum')->putJson("/api/operator/deals/{$deal->id}", ['strategies' => ['hmo', 'social-housing']])
            ->assertOk()
            ->assertJsonPath('deal.strategies.0.label', 'HMO')
            ->assertJsonPath('deal.strategies.1.label', 'Social housing');

        $this->actingAs($this->operator(), 'sanctum')->putJson("/api/operator/deals/{$deal->id}", ['strategies' => ['castle']])->assertJsonValidationErrors('strategies.0');

        // Saving the form without the field leaves the tags alone.
        $this->actingAs($this->operator(), 'sanctum')->putJson("/api/operator/deals/{$deal->id}", ['town' => 'Hove'])->assertOk();
        $this->assertCount(2, $deal->strategies()->get());
    }
}
