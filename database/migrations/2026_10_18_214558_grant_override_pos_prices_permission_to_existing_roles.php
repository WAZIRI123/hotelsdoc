<?php

declare(strict_types=1);

use App\Domain\Authorization\RolePermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adds the new override-pos-prices permission to every existing hotel's roles
 * that the catalog grants it to, without touching their other permissions.
 */
return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::findOrCreate('override-pos-prices', 'web');

        $roles = array_keys(array_filter(
            RolePermissionCatalog::ROLES,
            fn (array $permissions): bool => in_array('override-pos-prices', $permissions, true),
        ));

        Role::query()->whereIn('name', $roles)->get()->each(
            fn (Role $role) => $role->givePermissionTo($permission),
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', 'override-pos-prices')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
