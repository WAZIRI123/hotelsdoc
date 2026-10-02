<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Decides which hotel a user may work in.
 *
 * - Staff can enter any hotel they belong to, unless it is suspended.
 * - Super-admins can enter any hotel, including suspended ones, so they
 *   can inspect and support a tenant without being attached to it.
 *
 * The chosen hotel lives in the session under SESSION_KEY; when it is
 * missing or no longer allowed, the user's first active hotel is used.
 */
final class HotelAccess
{
    public const SESSION_KEY = 'current_hotel_id';

    public function canEnter(User $user, Hotel $hotel): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return ! $hotel->isSuspended()
            && $user->hotels()->whereKey($hotel->id)->exists();
    }

    /**
     * Hotels offered in the sidebar switcher: the user's own active hotels.
     *
     * @return Collection<int, Hotel>
     */
    public function switchableHotels(User $user): Collection
    {
        return $user->hotels()
            ->active()
            ->orderBy('hotels.name')
            ->get();
    }

    public function resolve(User $user, ?int $preferredHotelId): ?Hotel
    {
        if ($preferredHotelId !== null) {
            $preferred = Hotel::query()->find($preferredHotelId);
            if ($preferred !== null && $this->canEnter($user, $preferred)) {
                return $preferred;
            }
        }

        return $user->hotels()->active()->orderBy('hotels.name')->first();
    }
}
