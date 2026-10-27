<?php

namespace App\Http\Controllers\Operator;

use App\Enums\SourceType;
use App\Http\Controllers\Controller;
use App\Models\Source;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Manage the scouts' sources without a migration. Set active to false to pause one without deleting it. */
class SourceController extends Controller
{
    public function index(): JsonResponse
    {
        $sources = Source::orderBy('type')->inScoutOrder()->get();

        return response()->json(['data' => $sources->map->toOperator()]);
    }

    public function store(Request $request): JsonResponse
    {
        $source = Source::create($request->validate($this->rules()));

        return response()->json(['message' => 'Source added.', 'data' => $source->refresh()->toOperator()], 201);
    }

    public function update(Request $request, Source $source): JsonResponse
    {
        $source->update($request->validate($this->rules($source, partial: true)));

        return response()->json(['message' => 'Source saved.', 'data' => $source->toOperator()]);
    }

    public function destroy(Source $source): JsonResponse
    {
        $source->delete();

        return response()->json(['message' => 'Source deleted.']);
    }

    private function rules(?Source $source = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'type' => [$required, Rule::enum(SourceType::class)],
            'priority' => [$required, 'integer', 'min:0', 'max:1000'],
            'category' => [$required, 'string', 'max:120'],
            'name' => [$required, 'string', 'max:255'],
            'url' => [$required, 'url', 'max:2048', Rule::unique('sources', 'url')->ignore($source)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
