<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Actions;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates a new tenant (hotel) together with its first administrator.
 *
 * Hotel::created already seeds default payment methods and the per-hotel
 * role catalog, so this action only has to attach the admin and give them
 * the 'admin' role inside the new hotel's team scope.
 *
 * An existing user (matched by email) is attached instead of duplicated,
 * so one person can be the admin of several hotels.
 */
final class ProvisionHotel
{
    /**
     * @param  array{name: string, slug: string, currency: string, timezone: string, phone?: string|null, fax?: string|null, email?: string|null, address?: string|null, street?: string|null, tin?: string|null, vrn?: string|null}  $hotelAttributes
     * @param  array{name: string, email: string, password?: string|null}  $adminAttributes
     */
    public function execute(array $hotelAttributes, array $adminAttributes): Hotel
    {
        return DB::transaction(function () use ($hotelAttributes, $adminAttributes): Hotel {
            $hotel = Hotel::query()->create($hotelAttributes);

            $admin = User::query()->where('email', $adminAttributes['email'])->first();
            if ($admin === null) {
                $admin = User::query()->create([
                    'name' => $adminAttributes['name'],
                    'email' => $adminAttributes['email'],
                    'password' => Hash::make($adminAttributes['password'] ?? throw new InvalidArgumentException('A password is required for a new admin user.')),
                ]);
                $admin->forceFill(['email_verified_at' => now()])->save();
            }

            $hotel->users()->syncWithoutDetaching([$admin->id]);

            app(PermissionRegistrar::class)->setPermissionsTeamId($hotel->id);
            $admin->unsetRelation('roles');
            $admin->syncRoles(['admin']);

            return $hotel;
        });
    }
}
