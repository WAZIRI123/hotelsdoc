<?php

declare(strict_types=1);

use App\Domain\Authorization\RolePermissionCatalog;
use App\Models\Hotel;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hotels created before the accountant role existed never had it seeded.
 * Only the missing role is created, so hotels' customised roles stay untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permissions = RolePermissionCatalog::permissionsFor('accountant');

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Hotel::query()->get()->each(function (Hotel $hotel) use ($permissions): void {
            /** @var Role $role */
            $role = Role::query()->firstOrCreate(
                ['name' => 'accountant', 'guard_name' => 'web', 'hotel_id' => $hotel->id],
            );

            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($permissions);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->where('name', 'accountant')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
