<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DealController extends Controller
{
    public function show(Request $request, Deal $deal): JsonResponse
    {
        $this->authorizeView($request, $deal);

        return response()->json([
            'deal' => $deal->load('developmentType')->toMemberView(),
        ]);
    }

    /** The pack is rendered from the stored appraisal and stamped with the downloader's email. */
    public function pack(Request $request, Deal $deal)
    {
        $this->authorizeView($request, $deal);
        abort_unless($deal->hasPack(), 404);

        $pdf = Pdf::loadView('pack', [
            'deal' => $deal->load('developmentType')->toMemberView(),
            'email' => $request->user()->email,
            'stampedAt' => now(),
        ])->setPaper('a4');

        $name = Str::slug($deal->reference ?: 'deal-'.$deal->id).'-pack.pdf';

        return $pdf->download($name);
    }

    private function authorizeView(Request $request, Deal $deal): void
    {
        // Drafts do not exist as far as members are concerned.
        abort_unless($deal->isPublished() || $request->user()->isOperator(), 404);
    }
}
