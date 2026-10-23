<?php

namespace App\Services\Analyst;

use App\Enums\AnalystVerdict;

final readonly class AnalystResult
{
    public function __construct(
        public AnalystVerdict $verdict,
        public ?string $notes,
    ) {}
}
