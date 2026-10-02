<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the platform (cross-tenant) admin area. Runs without the tenant
 * middleware, so no TenantContext is set and tenant scopes are not applied.
 */
final class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSuperAdmin() ?? false, 403);

        return $next($request);
    }
}
