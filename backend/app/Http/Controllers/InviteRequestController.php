<?php

namespace App\Http\Controllers;

use App\Models\InviteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** "Request an invite" on the public page. Stored for the operator; nothing is sent. */
class InviteRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        InviteRequest::create($validated);

        return response()->json(['ok' => true], 201);
    }
}
