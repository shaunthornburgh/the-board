<?php

namespace App\Jobs;

use App\Enums\DealStatus;
use App\Models\Deal;
use App\Services\Analyst\AnalystClient;
use App\Services\DealPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Sends one deal to the Analyst bot and writes the verdict back. Dispatched by "Send to Analyst".
 *
 * Failures are recorded on the deal (analyst_error) straight away so the operator sees them, and the
 * job is released for another try. On the sync queue there is no retry; the operator clicks Retry.
 */
class RunAnalyst implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $dealId) {}

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(AnalystClient $analyst, DealPipeline $pipeline): void
    {
        $deal = Deal::find($this->dealId);

        // Rejected, or already answered (by a retry or by hand) since this was queued.
        if (! $deal || $deal->status !== DealStatus::AwaitingAnalyst) {
            return;
        }

        try {
            $result = $analyst->analyse($deal);
        } catch (Throwable $e) {
            report($e);
            $pipeline->recordAnalystFailure($deal, $e->getMessage());

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff()[$this->attempts() - 1] ?? 120);
            }

            return;
        }

        $pipeline->recordAnalystResult($deal, $result->verdict, $result->notes);
    }
}
