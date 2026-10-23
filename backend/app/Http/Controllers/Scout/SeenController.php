<?php

namespace App\Http\Controllers\Scout;

use App\Http\Controllers\Controller;
use App\Models\ScoutSeenListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** The server-side replacement for the scouts' "seen" CSV. A listing is identified by source + source_key. */
class SeenController extends Controller
{
    public const MAX_BATCH = 500;

    /** Mark listings as seen, whether or not they became candidates. */
    public function store(Request $request): JsonResponse
    {
        $items = $this->validated($request, withUrl: true);
        $scout = $request->user()->name;

        foreach ($items as $item) {
            ScoutSeenListing::mark($item['source'], $item['source_key'], $item['source_url'] ?? null, $scout);
        }

        return response()->json(['marked' => count($items)]);
    }

    /** Which of these listings have been seen before. Unseen ones are simply left out. */
    public function check(Request $request): JsonResponse
    {
        $items = $this->validated($request);

        $seen = ScoutSeenListing::query()
            ->where(function ($query) use ($items) {
                foreach (collect($items)->groupBy('source') as $source => $group) {
                    $query->orWhere(fn ($q) => $q->where('source', $source)->whereIn('source_key', $group->pluck('source_key')));
                }
            })
            ->get();

        return response()->json(['seen' => $seen->map->toApi()->values()]);
    }

    private function validated(Request $request, bool $withUrl = false): array
    {
        $request->validate([
            'listings' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'listings.*.source' => ['required', 'string', 'max:60'],
            'listings.*.source_key' => ['required', 'string', 'max:120'],
            ...($withUrl ? ['listings.*.source_url' => ['nullable', 'url', 'max:2048']] : []),
        ]);

        return collect($request->input('listings'))
            ->map(fn ($item) => [...$item, 'source' => Str::lower($item['source'])])
            ->all();
    }
}
