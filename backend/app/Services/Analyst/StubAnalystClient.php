<?php

namespace App\Services\Analyst;

use App\Enums\AnalystVerdict;
use App\Models\Deal;

/**
 * Stands in for the Grok Analyst bot until it is wired up (ANALYST_DRIVER=stub, the default).
 * The verdict is deterministic per deal so the pipeline can be exercised end to end.
 */
class StubAnalystClient implements AnalystClient
{
    public function analyse(Deal $deal): AnalystResult
    {
        if (blank($deal->address) && blank($deal->source_url)) {
            return new AnalystResult(AnalystVerdict::Reject, '[stub] Nothing to analyse: no address or listing URL.');
        }

        $verdicts = AnalystVerdict::cases();
        $verdict = $verdicts[crc32((string) ($deal->source_key ?? $deal->id)) % count($verdicts)];

        $where = $deal->address ?: $deal->source_url;

        return new AnalystResult($verdict, "[stub] Placeholder analysis of {$where}. Replace with the Grok Analyst bot (ANALYST_DRIVER=grok).");
    }
}
