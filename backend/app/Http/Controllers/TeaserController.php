<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use Illuminate\Http\JsonResponse;

/** The public index: published deals as teasers only. */
class TeaserController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $deals = Deal::published()
            ->with('developmentType')
            ->latest('published_at')
            ->latest('id')
            ->get()
            ->filter->hasPhoto()
            ->values();

        return response()->json(['deals' => $deals->map->toTeaser()]);
    }
}
