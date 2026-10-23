<?php

namespace App\Services\Analyst;

use App\Models\Deal;

/**
 * Asks the Analyst bot for a verdict on a candidate. Chosen by ANALYST_DRIVER (stub | grok),
 * bound in AppServiceProvider. Throw AnalystException for anything the operator should see.
 */
interface AnalystClient
{
    public function analyse(Deal $deal): AnalystResult;
}
