<?php

namespace App\Exceptions;

use App\Enums\DealStatus;
use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A pipeline move that is not allowed, or a deal not ready for it. Rendered as a 422 with the message. */
class PipelineException extends RuntimeException
{
    public static function cannotMove(Deal $deal, DealStatus $to): self
    {
        return new self("This deal is {$deal->status->label()}, so it cannot move to {$to->label()}.");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
