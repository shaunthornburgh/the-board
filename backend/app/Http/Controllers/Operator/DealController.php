<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DevelopmentType;
use App\Support\DealRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** JSON for the operator's deal screens in Nuxt. */
class DealController extends Controller
{
    public function index(): JsonResponse
    {
        $deals = Deal::with('developmentType')
            ->orderByRaw("case when status = 'draft' then 0 else 1 end")
            ->latest('updated_at')
            ->get()
            ->map(fn (Deal $deal) => $this->present($deal));

        return response()->json(['deals' => $deals]);
    }

    /** Paste a URL to open an empty draft for anything entered by hand. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_url' => ['required', 'url', 'max:2048'],
        ]);

        $deal = Deal::create([
            'development_type_id' => DevelopmentType::where('slug', 'pd')->value('id'),
            'status' => Deal::STATUS_DRAFT,
            'origin' => 'manual',
            'source_url' => $validated['source_url'],
            'source_name' => Str::of(parse_url($validated['source_url'], PHP_URL_HOST) ?? '')->after('www.')->value() ?: null,
        ]);

        return response()->json(['message' => 'Draft created. Fill in what you have.', 'deal' => $this->present($deal)], 201);
    }

    public function show(Deal $deal): JsonResponse
    {
        return response()->json([
            'deal' => $this->present($deal),
            'types' => DevelopmentType::orderBy('label')->get(['slug', 'label']),
            'verdicts' => Deal::VERDICTS,
            'tenures' => Deal::TENURES,
        ]);
    }

    public function update(Request $request, Deal $deal): JsonResponse
    {
        // Comps and evidence arrive as JSON text from the form.
        $request->merge([
            'comps' => $this->decodeJson($request, 'comps_json'),
            'evidence' => $this->decodeJson($request, 'evidence_json'),
        ]);

        $validated = $request->validate([
            ...DealRules::rules(),
            'teaser' => ['nullable', 'string', 'max:160'],
        ]);

        // The photo is set by upload only, so a published deal cannot lose it here.
        $deal->update(DealRules::attributes(collect($validated)->except('photo_url')->all()));

        return response()->json(['message' => 'Saved.', 'deal' => $this->present($deal)]);
    }

    /** Store the deal's own photo locally. Published deals need one. */
    public function uploadPhoto(Request $request, Deal $deal): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $file = $request->file('photo');
        $name = 'deal-'.$deal->id.'-'.Str::lower(Str::random(12)).'.'.$file->extension();
        Storage::disk('photos')->putFileAs('', $file, $name);

        $previous = $deal->photo_url;
        $deal->update(['photo_url' => '/api/photos/'.$name]);

        if ($previous && str_starts_with($previous, '/api/photos/')) {
            Storage::disk('photos')->delete(basename($previous));
        }

        return response()->json(['message' => 'Photo saved.', 'deal' => $this->present($deal)]);
    }

    public function publish(Deal $deal): JsonResponse
    {
        if (blank($deal->address)) {
            return response()->json(['message' => 'Add an address before publishing.'], 422);
        }

        if (! $deal->hasPhoto()) {
            return response()->json(['message' => 'Upload a photo of the property before publishing.'], 422);
        }

        $deal->publish();

        return response()->json(['message' => 'Published. Members can now see it.', 'deal' => $this->present($deal)]);
    }

    public function unpublish(Deal $deal): JsonResponse
    {
        $deal->unpublish();

        return response()->json(['message' => 'Unpublished. Members can no longer see it.', 'deal' => $this->present($deal)]);
    }

    public function generatePack(Deal $deal): JsonResponse
    {
        $deal->update(['pack_generated_at' => now()]);

        return response()->json(['message' => 'Pack generated from the stored appraisal.', 'deal' => $this->present($deal)]);
    }

    public function removePack(Deal $deal): JsonResponse
    {
        $deal->update(['pack_generated_at' => null]);

        return response()->json(['message' => 'Pack removed.', 'deal' => $this->present($deal)]);
    }

    /** The operator sees everything, including the bot's verdict and the raw teaser. */
    private function present(Deal $deal): array
    {
        $deal->loadMissing('developmentType');

        return [
            ...$deal->toPresentation(),
            'type' => $deal->developmentType?->slug,
            'type_label' => $deal->developmentType?->label,
            'tenure' => $deal->tenure,
            'tenure_label' => $deal->tenure ? (Deal::TENURES[$deal->tenure] ?? $deal->tenure) : null,
            'appraised_at' => $deal->appraised_at?->toDateString(),
            'teaser' => $deal->teaser,
            'has_photo' => $deal->hasPhoto(),
            'updated_at' => $deal->updated_at?->toIso8601String(),
        ];
    }

    private function decodeJson(Request $request, string $key): ?array
    {
        $raw = trim((string) $request->input($key));

        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([$key => 'This is not valid JSON.']);
        }

        return $decoded;
    }
}
