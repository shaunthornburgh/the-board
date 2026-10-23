<?php

namespace App\Http\Controllers\Operator;

use App\Enums\AnalystVerdict;
use App\Enums\DealStatus;
use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\DevelopmentType;
use App\Models\Strategy;
use App\Services\DealPipeline;
use App\Support\DealRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** JSON for the operator's deal screens in Nuxt: the pipeline, the edit form and the pipeline actions. */
class DealController extends Controller
{
    public function __construct(private readonly DealPipeline $pipeline) {}

    /** One status at a time (paginated), with a count for every status. Without ?status, every deal. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(DealStatus::class)],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $deals = Deal::with('developmentType', 'strategies')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('address', 'like', "%{$term}%")
                ->orWhere('town', 'like', "%{$term}%")
                ->orWhere('postcode', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhere('source_key', 'like', "%{$term}%")))
            ->latest('status_changed_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 100);

        $counts = Deal::toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'deals' => collect($deals->items())->map(fn (Deal $deal) => $this->present($deal)),
            'pagination' => [
                'current_page' => $deals->currentPage(),
                'last_page' => $deals->lastPage(),
                'total' => $deals->total(),
            ],
            'statuses' => collect(DealStatus::options())->map(fn ($s) => [...$s, 'count' => (int) ($counts[$s['value']] ?? 0)]),
        ]);
    }

    /** Paste a URL to open an empty deal for anything entered by hand. It starts as new, like a scout's. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_url' => ['required', 'url', 'max:2048'],
        ]);

        $deal = Deal::create([
            'development_type_id' => DevelopmentType::where('slug', 'pd')->value('id'),
            'origin' => 'manual',
            'source_url' => $validated['source_url'],
            'source_name' => Str::of(parse_url($validated['source_url'], PHP_URL_HOST) ?? '')->after('www.')->value() ?: null,
        ]);
        $this->pipeline->created($deal, $request->user(), note: 'Entered by hand');

        return response()->json(['message' => 'Deal created. Fill in what you have.', 'deal' => $this->present($deal)], 201);
    }

    public function show(Deal $deal): JsonResponse
    {
        $deal->load('statusEvents.user');

        return response()->json([
            'deal' => $this->present($deal),
            'history' => $deal->statusEvents->map->toApi(),
            'types' => DevelopmentType::orderBy('label')->get(['slug', 'label']),
            'strategies' => Strategy::orderBy('label')->get()->map->toOption(),
            'verdicts' => Deal::VERDICTS,
            'analyst_verdicts' => AnalystVerdict::options(),
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
            'strategies' => ['sometimes', 'nullable', 'array'],
            'strategies.*' => ['string', Rule::exists('strategies', 'slug')],
        ]);

        // The photo is set by upload only, so a published deal cannot lose it here.
        $deal->update(DealRules::attributes(collect($validated)->except(['photo_url', 'strategies'])->all()));

        if ($request->has('strategies')) {
            $deal->strategies()->sync(Strategy::whereIn('slug', $validated['strategies'] ?? [])->pluck('id'));
        }

        return response()->json(['message' => 'Saved.', 'deal' => $this->present($deal->refresh())]);
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

    public function reject(Request $request, Deal $deal): JsonResponse
    {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->pipeline->reject($deal, $request->user(), $validated['reason'] ?? null);

        return $this->done('Rejected.', $deal);
    }

    public function bulkReject(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->pipeline->each(
            Deal::whereIn('id', $validated['ids'])->get(),
            fn (Deal $deal) => $this->pipeline->reject($deal, $request->user(), $validated['reason'] ?? null),
        );

        return $this->bulkDone('Rejected', $result);
    }

    public function sendToAnalyst(Request $request, Deal $deal): JsonResponse
    {
        $this->pipeline->sendToAnalyst($deal, $request->user());

        return $this->done($this->analystMessage($deal), $deal);
    }

    public function bulkSendToAnalyst(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        $result = $this->pipeline->each(
            Deal::whereIn('id', $validated['ids'])->get(),
            fn (Deal $deal) => $this->pipeline->sendToAnalyst($deal, $request->user()),
        );

        return $this->bulkDone('Sent to the analyst', $result);
    }

    /** Record the analyst's verdict by hand, e.g. when the bot answered outside the app. */
    public function analystResult(Request $request, Deal $deal): JsonResponse
    {
        $validated = $request->validate([
            'verdict' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! AnalystVerdict::fromBot($value)) {
                    $fail('The verdict must be send, mention or reject (KILL counts as reject).');
                }
            }],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $this->pipeline->recordAnalystResult($deal, AnalystVerdict::fromBot($validated['verdict']), $validated['notes'] ?? null, $request->user());

        return $this->done('Analyst result recorded.', $deal);
    }

    public function moveToReview(Request $request, Deal $deal): JsonResponse
    {
        $this->pipeline->moveToReview($deal, $request->user());

        return $this->done('Moved to awaiting review.', $deal);
    }

    public function publish(Request $request, Deal $deal): JsonResponse
    {
        $this->pipeline->publish($deal, $request->user());

        return $this->done('Published. Members can now see it.', $deal);
    }

    public function unpublish(Request $request, Deal $deal): JsonResponse
    {
        $this->pipeline->unpublish($deal, $request->user());

        return $this->done('Unpublished. Members can no longer see it; it is back in awaiting review.', $deal);
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

    private function analystMessage(Deal $deal): string
    {
        $deal->refresh();

        return match (true) {
            $deal->status === DealStatus::AnalystDone => 'Analyst verdict: '.$deal->analyst_verdict->label().'.',
            filled($deal->analyst_error) => 'Sent to the analyst, but it failed: '.$deal->analyst_error,
            default => 'Sent to the analyst. The verdict will appear here when it is back.',
        };
    }

    private function done(string $message, Deal $deal): JsonResponse
    {
        $deal->refresh()->load('statusEvents.user');

        return response()->json([
            'message' => $message,
            'deal' => $this->present($deal),
            'history' => $deal->statusEvents->map->toApi(),
        ]);
    }

    private function bulkDone(string $verb, array $result): JsonResponse
    {
        $done = count($result['done']);
        $skipped = count($result['skipped']);
        $message = "{$verb} {$done} ".Str::plural('deal', $done).'.'
            .($skipped ? " Skipped {$skipped}: ".collect($result['skipped'])->unique()->implode(' ') : '');

        return response()->json(['message' => $message, ...$result]);
    }

    /** The operator sees everything, including the verdicts, analyst output and the raw teaser. */
    private function present(Deal $deal): array
    {
        $deal->loadMissing('developmentType', 'strategies');

        return [
            ...$deal->toPresentation(),
            'status_label' => $deal->status->label(),
            'status_changed_at' => $deal->status_changed_at?->toIso8601String(),
            'can_reject' => $deal->status->canReject(),
            'next' => array_map(fn (DealStatus $s) => $s->value, $deal->status->next()),
            'type' => $deal->developmentType?->slug,
            'type_label' => $deal->developmentType?->label,
            'strategies' => $deal->strategies->map->toOption()->values(),
            'tenure' => $deal->tenure,
            'tenure_label' => $deal->tenure ? (Deal::TENURES[$deal->tenure] ?? $deal->tenure) : null,
            'appraised_at' => $deal->appraised_at?->toDateString(),
            'teaser' => $deal->teaser,
            'has_photo' => $deal->hasPhoto(),
            'scout' => $deal->scout,
            'source' => $deal->source,
            'source_key' => $deal->source_key,
            'scout_notes' => $deal->scout_notes,
            'analyst_verdict' => $deal->analyst_verdict?->value,
            'analyst_verdict_label' => $deal->analyst_verdict?->label(),
            'analyst_notes' => $deal->analyst_notes,
            'analyst_requested_at' => $deal->analyst_requested_at?->toIso8601String(),
            'analyst_completed_at' => $deal->analyst_completed_at?->toIso8601String(),
            'analyst_error' => $deal->analyst_error,
            'rejected_at' => $deal->rejected_at?->toIso8601String(),
            'rejection_reason' => $deal->rejection_reason,
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
