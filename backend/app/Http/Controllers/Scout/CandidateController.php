<?php

namespace App\Http\Controllers\Scout;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DevelopmentType;
use App\Models\ScoutSeenListing;
use App\Models\Strategy;
use App\Services\DealPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Scouts push candidates here instead of writing Google Drive CSVs. Each candidate becomes a `new` deal,
 * unless that listing (source + source_key) is already a deal. Results are per candidate, so one bad row
 * does not reject the batch.
 */
class CandidateController extends Controller
{
    public const MAX_BATCH = 100;

    public function store(Request $request, DealPipeline $pipeline): JsonResponse
    {
        $request->validate([
            'candidates' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'candidates.*' => ['array'],
        ]);

        $scout = $request->user()->name;
        $results = [];

        foreach ($request->input('candidates') as $index => $input) {
            $results[] = ['index' => $index, ...$this->ingest((array) $input, $scout, $pipeline)];
        }

        $counts = collect($results)->countBy('result');

        return response()->json([
            'created' => $counts->get('created', 0),
            'duplicates' => $counts->get('duplicate', 0),
            'invalid' => $counts->get('invalid', 0),
            'results' => $results,
        ], $counts->get('created', 0) > 0 ? 201 : 200);
    }

    private function ingest(array $input, string $scout, DealPipeline $pipeline): array
    {
        $validator = Validator::make($input, [
            'source' => ['required', 'string', 'max:60'],
            'source_key' => ['required', 'string', 'max:120'],
            'source_url' => ['nullable', 'url', 'max:2048'],
            'source_name' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'town' => ['nullable', 'string', 'max:120'],
            'county' => ['nullable', 'string', 'max:120'],
            'postcode' => ['nullable', 'string', 'max:12'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'asking_price' => ['nullable', 'integer', 'min:0'],
            'tenure' => ['nullable', Rule::in(array_keys(Deal::TENURES))],
            'type' => ['nullable', 'string', Rule::exists('development_types', 'slug')],
            'strategies' => ['nullable', 'array', 'max:10'],
            'strategies.*' => ['string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'payload' => ['nullable', 'array'],
        ]);

        [$strategies, $unknown] = Strategy::resolve($input['strategies'] ?? []);
        $validator->after(function ($v) use ($unknown) {
            if ($unknown) {
                $v->errors()->add('strategies', 'Unknown strategy: '.implode(', ', $unknown).'. Known: '.Strategy::pluck('label')->implode(', ').'.');
            }
        });

        if ($validator->fails()) {
            return [
                'result' => 'invalid',
                'source_key' => $input['source_key'] ?? null,
                'errors' => $validator->errors()->toArray(),
            ];
        }

        $data = $validator->validated();
        $source = Str::lower($data['source']);

        return DB::transaction(function () use ($data, $source, $scout, $strategies, $pipeline) {
            $existing = Deal::where('source', $source)->where('source_key', $data['source_key'])->first();

            if ($existing) {
                ScoutSeenListing::mark($source, $data['source_key'], $data['source_url'] ?? null, $scout, $existing->id);

                return ['result' => 'duplicate', 'source_key' => $data['source_key'], 'deal_id' => $existing->id, 'status' => $existing->status->value];
            }

            $deal = Deal::create([
                'development_type_id' => DevelopmentType::where('slug', $data['type'] ?? 'pd')->value('id'),
                'origin' => 'scout',
                'scout' => $scout,
                'source' => $source,
                'source_key' => $data['source_key'],
                'source_url' => $data['source_url'] ?? null,
                'source_name' => $data['source_name'] ?? $this->hostOf($data['source_url'] ?? null),
                'address' => $data['address'] ?? null,
                'town' => $data['town'] ?? null,
                'county' => $data['county'] ?? null,
                'postcode' => $data['postcode'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'asking_price' => $data['asking_price'] ?? null,
                'tenure' => $data['tenure'] ?? null,
                'scout_notes' => $data['notes'] ?? null,
                'scout_payload' => $data['payload'] ?? null,
            ]);

            $deal->strategies()->sync($strategies->pluck('id'));
            $pipeline->created($deal, actor: $scout, note: 'Pushed by scout');
            ScoutSeenListing::mark($source, $data['source_key'], $data['source_url'] ?? null, $scout, $deal->id);

            return ['result' => 'created', 'source_key' => $data['source_key'], 'deal_id' => $deal->id, 'status' => $deal->status->value];
        });
    }

    private function hostOf(?string $url): ?string
    {
        return $url ? (Str::of(parse_url($url, PHP_URL_HOST) ?? '')->after('www.')->value() ?: null) : null;
    }
}
