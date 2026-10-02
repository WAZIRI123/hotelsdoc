<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\HotelAccess;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Resolves the active Hotel for the authenticated user and registers it on
 * the TenantContext singleton.
 *
 * The hotel picked in the sidebar switcher is kept in the session; it is
 * re-checked on every request so removing a user from a hotel, or
 * suspending the hotel, takes effect immediately.
 */
final class SetCurrentHotel
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly HotelAccess $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }

        $preferredHotelId = $request->hasSession() ? $request->session()->get(HotelAccess::SESSION_KEY) : null;
        $hotel = $this->access->resolve($user, is_numeric($preferredHotelId) ? (int) $preferredHotelId : null);

        if ($hotel === null) {
            if ($user->isSuperAdmin()) {
                return redirect()->route('admin.hotels.index');
            }

            $message = $user->hotels()->exists()
                ? 'This hotel has been suspended. Please contact support.'
                : 'No hotel is linked to this account.';

            throw new HttpException(403, $message);
        }

        if ($request->hasSession()) {
            $request->session()->put(HotelAccess::SESSION_KEY, $hotel->id);
        }

        $this->context->set($hotel);

        return $next($request);
    }
}
