<?php

namespace App\Support;

use App\Models\Deal;
use App\Models\DevelopmentType;
use Illuminate\Validation\Rule;

/** Validation for the fields of a deal, used by the operator's edit form. */
class DealRules
{
    public static function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::exists('development_types', 'slug')],
            'address' => ['nullable', 'string', 'max:255'],
            'town' => ['nullable', 'string', 'max:120'],
            'county' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:12'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo_url' => ['nullable', 'string', 'max:2048'],
            'asking_price' => ['nullable', 'integer', 'min:0'],
            'tenure' => ['nullable', Rule::in(array_keys(Deal::TENURES))],
            'source_name' => ['nullable', 'string', 'max:120'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'verdict' => ['nullable', Rule::in(array_keys(Deal::VERDICTS))],
            'bot_note' => ['nullable', 'string', 'max:5000'],
            'tenant' => ['nullable', 'string', 'max:255'],
            'vacant_area_sq_ft' => ['nullable', 'integer', 'min:0'],
            'comps' => ['nullable', 'array', 'max:20'],
            'comps.*' => ['array'],
            'comps.*.address' => ['nullable', 'string', 'max:255'],
            'comps.*.price' => ['nullable', 'integer', 'min:0'],
            'comps.*.date' => ['nullable', 'string', 'max:40'],
            'comps.*.note' => ['nullable', 'string', 'max:500'],
            'evidence' => ['nullable', 'array', 'max:40'],
            'evidence.*' => ['nullable', 'string', 'max:1000'],
            'appraised_at' => ['nullable', 'date'],
        ];
    }

    /** Map validated input onto deal attributes, resolving the type slug. */
    public static function attributes(array $validated): array
    {
        $attributes = collect($validated)->except('type')->all();

        if (array_key_exists('type', $validated)) {
            $attributes['development_type_id'] = DevelopmentType::where('slug', $validated['type'] ?? 'pd')->value('id');
        }

        return $attributes;
    }
}
