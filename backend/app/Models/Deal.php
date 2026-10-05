<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference', 'development_type_id', 'status', 'origin', 'is_sample',
    'address', 'town', 'county', 'postcode', 'latitude', 'longitude', 'photo_url',
    'asking_price', 'tenure', 'source_name', 'source_url',
    'verdict', 'bot_note', 'teaser', 'tenant', 'vacant_area_sq_ft', 'comps', 'evidence', 'appraised_at',
    'pack_generated_at', 'published_at',
])]
class Deal extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const VERDICTS = ['send' => 'Send', 'mention' => 'Mention'];

    public const TENURES = ['freehold' => 'Freehold', 'leasehold' => 'Leasehold'];

    protected function casts(): array
    {
        return [
            'is_sample' => 'boolean',
            'latitude' => 'float',
            'longitude' => 'float',
            'asking_price' => 'integer',
            'vacant_area_sq_ft' => 'integer',
            'comps' => 'array',
            'evidence' => 'array',
            'appraised_at' => 'datetime',
            'pack_generated_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function developmentType(): BelongsTo
    {
        return $this->belongsTo(DevelopmentType::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function hasPack(): bool
    {
        return $this->pack_generated_at !== null;
    }

    public function publish(): void
    {
        $this->update(['status' => self::STATUS_PUBLISHED, 'published_at' => now()]);
    }

    public function unpublish(): void
    {
        $this->update(['status' => self::STATUS_DRAFT, 'published_at' => null]);
    }

    /** A photo counts only when it is served by this app: a bundled sample or an operator upload. */
    public function hasPhoto(): bool
    {
        return filled($this->photo_url)
            && (str_starts_with($this->photo_url, '/images/deals/') || str_starts_with($this->photo_url, '/api/photos/'));
    }

    /** Photos are served by the API, so the site gets an absolute URL on the API host. */
    public function photoUrl(): ?string
    {
        return $this->photo_url && str_starts_with($this->photo_url, '/') ? url($this->photo_url) : $this->photo_url;
    }

    /** The one line on the public index, falling back to type and tenure. */
    public function teaserLine(): string
    {
        if (filled($this->teaser)) {
            return $this->teaser;
        }

        $tenure = $this->tenure ? (self::TENURES[$this->tenure] ?? $this->tenure) : null;

        return collect([$tenure, $this->developmentType?->label])->filter()->implode(' · ') ?: 'Appraised opportunity';
    }

    /** What a logged-out visitor sees. No address, comps, tenant, vacant area, pack or verdict. */
    public function toTeaser(): array
    {
        return [
            'id' => $this->id,
            'is_sample' => $this->is_sample,
            'photo_url' => $this->photoUrl(),
            'town' => $this->town,
            'county' => $this->county,
            'asking_price' => $this->asking_price,
            'teaser' => $this->teaserLine(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }

    /** What members see on the board, the deal page and the pack. The bot's verdict is for the operator only. */
    public function toMemberView(): array
    {
        return collect($this->toPresentation())->except(['verdict', 'verdict_label'])->all();
    }

    public function displayTitle(): string
    {
        return $this->address ?: ($this->source_url ? 'Untitled draft' : 'Untitled deal');
    }

    /** Fields shared by the board, the deal page and the pack. */
    public function toPresentation(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'origin' => $this->origin,
            'is_sample' => $this->is_sample,
            'title' => $this->displayTitle(),
            'address' => $this->address,
            'town' => $this->town,
            'county' => $this->county,
            'postcode' => $this->postcode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'photo_url' => $this->photoUrl(),
            'asking_price' => $this->asking_price,
            'tenure' => $this->tenure ? (self::TENURES[$this->tenure] ?? $this->tenure) : null,
            'type' => $this->developmentType?->label,
            'source_name' => $this->source_name,
            'source_url' => $this->source_url,
            'verdict' => $this->verdict,
            'verdict_label' => $this->verdict ? (self::VERDICTS[$this->verdict] ?? $this->verdict) : null,
            'bot_note' => $this->bot_note,
            'teaser' => $this->teaserLine(),
            'tenant' => $this->tenant,
            'vacant_area_sq_ft' => $this->vacant_area_sq_ft,
            'comps' => $this->comps ?? [],
            'evidence' => $this->evidence ?? [],
            'appraised_at' => $this->appraised_at?->toDateString(),
            'has_pack' => $this->hasPack(),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }
}
