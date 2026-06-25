<?php

declare(strict_types=1);

use App\Domain\Authorization\RolePermissionCatalog;
use App\Livewire\Settings\Roles\Index;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('seeds the default role catalog for every new hotel', function (): void {
    [$hotel] = actingAsTenantUser();

    $names = Role::query()->where('hotel_id', $hotel->id)->pluck('name')->all();

    foreach (RolePermissionCatalog::roles() as $expected) {
        expect($names)->toContain($expected);
    }
});

it('updates the permissions of a role', function (): void {
    [$hotel] = actingAsTenantUser();

    $waiter = Role::query()->where('hotel_id', $hotel->id)->where('name', 'waiter')->firstOrFail();

    Livewire::test(Index::class)
        ->call('openEdit', $waiter->id)
        ->set('selectedPermissions', ['operate-pos', 'view-reports'])
        ->call('save')
        ->assertHasNoErrors();

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect($waiter->fresh()->permissions->pluck('name')->all())
        ->toContain('operate-pos')
        ->toContain('view-reports');
});

it('refuses to strip the admin role of any permissions', function (): void {
    [$hotel] = actingAsTenantUser();

    $admin = Role::query()->where('hotel_id', $hotel->id)->where('name', 'admin')->firstOrFail();

    Livewire::test(Index::class)
        ->call('openEdit', $admin->id)
        ->set('selectedPermissions', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($admin->fresh()->permissions->count())->toBe(count(RolePermissionCatalog::PERMISSIONS));
});

it('rejects non-admin users from the roles page', function (): void {
    actingAsTenantUser(role: 'manager');

    Livewire::test(Index::class)->assertStatus(403);
});
