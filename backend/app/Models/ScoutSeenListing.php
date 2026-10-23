<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A listing a scout has already looked at, so it is not screened twice. */
#[Fillable(['source', 'source_key', 'source_url', 'scout', 'deal_id', 'first_seen_at', 'last_seen_at'])]
class ScoutSeenListing extends Model
{
    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** Record a sighting: creates the row the first time, bumps last_seen_at after that. */
    public static function mark(string $source, string $sourceKey, ?string $sourceUrl, ?string $scout, ?int $dealId = null): self
    {
        $seen = self::firstOrNew(['source' => $source, 'source_key' => $sourceKey]);

        $seen->fill([
            'source_url' => $sourceUrl ?? $seen->source_url,
            'scout' => $seen->scout ?? $scout,
            'deal_id' => $dealId ?? $seen->deal_id,
            'first_seen_at' => $seen->first_seen_at ?? now(),
            'last_seen_at' => now(),
        ])->save();

        return $seen;
    }

    public function toApi(): array
    {
        return [
            'source' => $this->source,
            'source_key' => $this->source_key,
            'source_url' => $this->source_url,
            'scout' => $this->scout,
            'deal_id' => $this->deal_id,
            'first_seen_at' => $this->first_seen_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
