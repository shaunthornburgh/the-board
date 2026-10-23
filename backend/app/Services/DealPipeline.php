<?php

namespace App\Services;

use App\Enums\AnalystVerdict;
use App\Enums\DealStatus;
use App\Exceptions\PipelineException;
use App\Jobs\RunAnalyst;
use App\Models\Deal;
use App\Models\DealStatusEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Every status change goes through here, so the allowed moves (DealStatus::next()) and the
 * audit trail (deal_status_events) live in one place.
 */
class DealPipeline
{
    /** Record the first status of a deal that has just been created. */
    public function created(Deal $deal, ?User $by = null, ?string $actor = null, ?string $note = null): void
    {
        $deal->forceFill(['status_changed_at' => now()])->save();
        $this->log($deal, null, $deal->status, $by, $actor, $note);
    }

    public function reject(Deal $deal, ?User $by, ?string $reason = null): Deal
    {
        return $this->move($deal, DealStatus::Rejected, $by, [
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ], note: $reason);
    }

    /** new → awaiting_analyst, then run the Analyst job. While still awaiting the analyst, this retries. */
    public function sendToAnalyst(Deal $deal, ?User $by): Deal
    {
        if ($deal->status !== DealStatus::AwaitingAnalyst) {
            $this->move($deal, DealStatus::AwaitingAnalyst, $by, [
                'analyst_requested_at' => now(),
                'analyst_error' => null,
            ]);
        } else {
            $deal->update(['analyst_requested_at' => now(), 'analyst_error' => null]);
        }

        RunAnalyst::dispatch($deal->id);

        return $deal->refresh();
    }

    /** awaiting_analyst → analyst_done, from the job or entered by the operator. */
    public function recordAnalystResult(Deal $deal, AnalystVerdict $verdict, ?string $notes, ?User $by = null, ?string $actor = 'analyst'): Deal
    {
        $attributes = [
            'analyst_verdict' => $verdict,
            'analyst_notes' => $notes,
            'analyst_completed_at' => now(),
            'analyst_error' => null,
        ];

        // Prefill the operator-only verdict and appraisal date if the operator has not set them.
        // bot_note is member-facing, so the analyst's raw notes are never copied into it.
        if ($deal->verdict === null && $verdict !== AnalystVerdict::Reject) {
            $attributes['verdict'] = $verdict->value;
        }
        if ($deal->appraised_at === null) {
            $attributes['appraised_at'] = now();
        }

        return $this->move($deal, DealStatus::AnalystDone, $by, $attributes, actor: $by ? null : $actor, note: 'Verdict: '.$verdict->label());
    }

    /** The analyst call failed. The deal stays awaiting the analyst so it can be retried. */
    public function recordAnalystFailure(Deal $deal, string $error): Deal
    {
        $deal->update(['analyst_error' => mb_substr($error, 0, 2000)]);

        return $deal;
    }

    public function moveToReview(Deal $deal, ?User $by): Deal
    {
        return $this->move($deal, DealStatus::AwaitingReview, $by);
    }

    public function publish(Deal $deal, ?User $by): Deal
    {
        if (! $deal->status->canMoveTo(DealStatus::Published)) {
            throw PipelineException::cannotMove($deal, DealStatus::Published);
        }
        if (blank($deal->address)) {
            throw new PipelineException('Add an address before publishing.');
        }
        if (! $deal->hasPhoto()) {
            throw new PipelineException('Upload a photo of the property before publishing.');
        }

        return $this->move($deal, DealStatus::Published, $by, ['published_at' => now()]);
    }

    /** published → awaiting_review. Members stop seeing it straight away. */
    public function unpublish(Deal $deal, ?User $by): Deal
    {
        if ($deal->status !== DealStatus::Published) {
            throw new PipelineException('This deal is not published.');
        }

        return $this->move($deal, DealStatus::AwaitingReview, $by, ['published_at' => null], note: 'Unpublished');
    }

    /**
     * Run one action over many deals. Deals that cannot make the move are skipped, not failed.
     *
     * @return array{done: list<int>, skipped: array<int, string>}
     */
    public function each(iterable $deals, callable $action): array
    {
        $done = [];
        $skipped = [];

        foreach ($deals as $deal) {
            try {
                $action($deal);
                $done[] = $deal->id;
            } catch (PipelineException $e) {
                $skipped[$deal->id] = $e->getMessage();
            }
        }

        return ['done' => $done, 'skipped' => $skipped];
    }

    private function move(Deal $deal, DealStatus $to, ?User $by, array $attributes = [], ?string $actor = null, ?string $note = null): Deal
    {
        return DB::transaction(function () use ($deal, $to, $by, $attributes, $actor, $note) {
            // Read the stored status, not the in-memory one, and only move if it is still that status,
            // so two clicks (or the job and the operator) cannot both move the same deal.
            $from = DealStatus::from(Deal::whereKey($deal->id)->toBase()->value('status'));
            $deal->status = $from;

            if (! $from->canMoveTo($to)) {
                throw PipelineException::cannotMove($deal, $to);
            }

            $moved = Deal::whereKey($deal->id)->where('status', $from->value)->toBase()->update(['status' => $to->value]);
            if ($moved === 0) {
                throw new PipelineException('This deal changed while you were looking at it. Refresh and try again.');
            }

            $deal->forceFill([...$attributes, 'status' => $to, 'status_changed_at' => now()])->save();
            $this->log($deal, $from, $to, $by, $actor, $note);

            return $deal;
        });
    }

    private function log(Deal $deal, ?DealStatus $from, DealStatus $to, ?User $by, ?string $actor, ?string $note): void
    {
        DealStatusEvent::create([
            'deal_id' => $deal->id,
            'from' => $from,
            'to' => $to,
            'user_id' => $by?->id,
            'actor' => $by ? null : $actor,
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
