<?php

namespace App\Models;

use App\Enums\SourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A site the scouts work through: an auction house or a property portal. Replaces the scouts' target-list CSVs. */
#[Fillable(['type', 'priority', 'category', 'name', 'url', 'notes', 'active'])]
class Source extends Model
{
    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return [
            'type' => SourceType::class,
            'priority' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /** Lowest priority number first, then by name. */
    public function scopeInScoutOrder(Builder $query): void
    {
        $query->orderBy('priority')->orderBy('name');
    }

    /** What a scout sees. */
    public function toScout(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'priority' => $this->priority,
            'category' => $this->category,
            'name' => $this->name,
            'url' => $this->url,
            'notes' => $this->notes,
        ];
    }

    /** What the operator sees: everything, including inactive sources. */
    public function toOperator(): array
    {
        return [
            ...$this->toScout(),
            'active' => $this->active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
