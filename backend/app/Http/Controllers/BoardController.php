<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'county' => ['nullable', 'string', 'max:120'],
        ]);

        $deals = Deal::published()
            ->with('developmentType')
            ->when($filters['county'] ?? null, fn ($q, $county) => $q->where('county', $county))
            ->latest('published_at')
            ->latest('id')
            ->get();

        return response()->json([
            'deals' => $deals->map->toMemberView(),
            'filters' => [
                'county' => $filters['county'] ?? '',
            ],
            'counties' => Deal::published()->whereNotNull('county')->distinct()->orderBy('county')->pluck('county'),
        ]);
    }
}
