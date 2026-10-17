<?php

namespace Modules\Identity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Identity\Http\ApiError;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->refresh()->active) {
            return ApiError::make(403, 'PERMISSION_DENIED', 'ACCOUNT_INACTIVE', 'This account is inactive.');
        }

        return $next($request);
    }
}
