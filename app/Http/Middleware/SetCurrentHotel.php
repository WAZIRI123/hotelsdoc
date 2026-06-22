<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Models\Hotel;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves the active Hotel for the authenticated user and registers it on
 * the TenantContext singleton.
 *
 * MVP: a user belongs to exactly one hotel. When we add multi-hotel switching,
 * read a hotel_id from session instead.
 */
final class SetCurrentHotel
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        /** @var Hotel|null $hotel */
        $hotel = $user->hotels()->first();
        if ($hotel === null) {
            throw new HttpException(403, 'No hotel is linked to this account.');
        }

        $this->context->set($hotel);

        return $next($request);
    }
}
