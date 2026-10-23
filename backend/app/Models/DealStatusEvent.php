<?php

namespace App\Models;

use App\Enums\DealStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One status change on a deal: the audit trail for the pipeline. */
#[Fillable(['deal_id', 'from', 'to', 'user_id', 'actor', 'note', 'created_at'])]
class DealStatusEvent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from' => DealStatus::class,
            'to' => DealStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'from' => $this->from?->value,
            'from_label' => $this->from?->label(),
            'to' => $this->to->value,
            'to_label' => $this->to->label(),
            'by' => $this->user?->name ?? $this->actor,
            'note' => $this->note,
            'at' => $this->created_at?->toIso8601String(),
        ];
    }
}
