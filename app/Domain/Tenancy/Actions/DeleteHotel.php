<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Models\Hotel;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permanently deletes a hotel and everything that belongs to it.
 *
 * Every tenant table has a cascading hotel_id foreign key, so deleting the
 * hotel row removes rooms, guests, reservations, guest billing, payments, POS data
 * and the hotel_user links. Spatie's per-hotel roles have no such key, so
 * they are removed here (role assignments cascade from the roles).
 *
 * User accounts are kept: they may belong to other hotels.
 *
 * Only a suspended hotel can be deleted, so deletion is always a deliberate
 * two-step operation.
 */
final class DeleteHotel
{
    public function execute(Hotel $hotel): void
    {
        if (! $hotel->isSuspended()) {
            throw new RuntimeException('Suspend the hotel before deleting it.');
        }

        DB::transaction(function () use ($hotel): void {
            Role::query()->where('hotel_id', $hotel->id)->delete();
            $hotel->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
