<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanSeeBoard
{
    public function handle(Request $request, Closure $next): Response
    {
        // Invited members wait on their settings page until the operator activates them.
        abort_unless($request->user()?->canSeeBoard(), 403, 'Your membership is not active yet.');

        return $next($request);
    }
}
