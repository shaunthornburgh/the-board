<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Scout endpoints: service accounts only. An operator's login token has every ability, so the role is checked too. */
class EnsureService
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isService(), 403);

        return $next($request);
    }
}
