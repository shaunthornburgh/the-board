<?php

namespace App\Http\Controllers\Scout;

use App\Enums\SourceType;
use App\Http\Controllers\Controller;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The scouts' target lists: active sources, lowest priority number first, then by name. */
class SourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::enum(SourceType::class)],
        ]);

        $sources = Source::active()
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->inScoutOrder()
            ->get();

        return response()->json(['data' => $sources->map->toScout()]);
    }
}
