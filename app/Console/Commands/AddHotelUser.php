<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Authorization\RolePermissionCatalog;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Signature('app:add-hotel-user {hotel : Hotel slug or id} {email : Email of the user (created if missing)} {--role=staff : Role to give the user in that hotel} {--name= : Name for a new user} {--password= : Password for a new user}')]
#[Description('Add a user to an existing hotel with the given role, creating the role from the catalog if the hotel lacks it')]
class AddHotelUser extends Command
{
    public function handle(PermissionRegistrar $registrar): int
    {
        $hotelKey = (string) $this->argument('hotel');
        $hotel = Hotel::query()->where('slug', $hotelKey)->orWhere('id', ctype_digit($hotelKey) ? (int) $hotelKey : 0)->first();

        if ($hotel === null) {
            $this->error("No hotel with slug or id [{$hotelKey}].");

            return self::FAILURE;
        }

        $roleName = (string) $this->option('role');

        if (! in_array($roleName, RolePermissionCatalog::roles(), true)) {
            $this->error("Unknown role [{$roleName}]. Choose one of: ".implode(', ', RolePermissionCatalog::roles()).'.');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $name = (string) ($this->option('name') ?: $this->ask('Name'));
            $password = (string) ($this->option('password') ?: $this->secret('Password (min 8 characters)'));

            if ($name === '' || strlen($password) < 8) {
                $this->error('A name and a password of at least 8 characters are required to create a user.');

                return self::FAILURE;
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        /** @var Role $role */
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName, 'guard_name' => 'web', 'hotel_id' => $hotel->id],
        );

        if ($role->wasRecentlyCreated) {
            $role->syncPermissions(RolePermissionCatalog::permissionsFor($roleName));
        }

        $hotel->users()->syncWithoutDetaching([$user->id]);

        $registrar->setPermissionsTeamId($hotel->id);
        $user->unsetRelation('roles');
        $user->syncRoles([$roleName]);
        $registrar->forgetCachedPermissions();

        $this->info("{$email} is now [{$roleName}] at {$hotel->name}.");

        return self::SUCCESS;
    }
}
