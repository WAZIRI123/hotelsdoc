<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Listeners;

use App\Domain\Authorization\RolePermissionCatalog;
use App\Models\Hotel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the catalog of roles and permissions for a hotel.
 *
 * Permissions are global (one row per permission across the system) because
 * Spatie's "teams" feature only scopes the roles + assignments, not the
 * permissions themselves. Roles are created per hotel (team_foreign_key set
 * to the hotel id) so a hotel can later customise its own role permissions
 * without affecting other hotels.
 */
final class SeedHotelRoles
{
    public function handle(Hotel $hotel): void
    {
        foreach (RolePermissionCatalog::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (RolePermissionCatalog::ROLES as $name => $permissions) {
            /** @var Role $role */
            $role = Role::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web', 'hotel_id' => $hotel->id],
            );

            $role->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
