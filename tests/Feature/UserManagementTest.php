<?php

declare(strict_types=1);

use App\Livewire\Settings\Users\Index;
use App\Models\Hotel;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

it('lists users in the current hotel', function (): void {
    [$hotel] = actingAsTenantUser();
    User::factory()->create(['name' => 'Other Hotel Person']);
    $sibling = User::factory()->create(['name' => 'Sibling User']);
    $hotel->users()->syncWithoutDetaching([$sibling->id]);

    Livewire::test(Index::class)
        ->assertSee('Sibling User')
        ->assertDontSee('Other Hotel Person');
});

it('creates a user and assigns the role in the current hotel', function (): void {
    [$hotel] = actingAsTenantUser();

    Livewire::test(Index::class)
        ->call('openCreate')
        ->set('name', 'New Front Desk')
        ->set('email', 'newfd@example.com')
        ->set('password', 'password123')
        ->set('role', 'front_desk')
        ->call('save')
        ->assertHasNoErrors();

    $user = User::query()->where('email', 'newfd@example.com')->firstOrFail();
    expect($user->name)->toBe('New Front Desk');
    expect($hotel->users()->where('users.id', $user->id)->exists())->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId($hotel->id);
    expect($user->fresh()->hasRole('front_desk'))->toBeTrue();
});

it('blocks non-admin users from the user management page', function (): void {
    actingAsTenantUser(role: 'front_desk');

    Livewire::test(Index::class)->assertStatus(403);
});

it('keeps roles scoped per hotel', function (): void {
    $hotelA = Hotel::factory()->create();
    $hotelB = Hotel::factory()->create();
    $user = User::factory()->create();
    $hotelA->users()->syncWithoutDetaching([$user->id]);
    $hotelB->users()->syncWithoutDetaching([$user->id]);

    $registrar = app(PermissionRegistrar::class);

    $registrar->setPermissionsTeamId($hotelA->id);
    $user->syncRoles(['admin']);

    $registrar->setPermissionsTeamId($hotelB->id);
    $user->unsetRelation('roles');
    $user->syncRoles(['waiter']);

    $registrar->setPermissionsTeamId($hotelA->id);
    $user->unsetRelation('roles');
    expect($user->hasRole('admin'))->toBeTrue();
    expect($user->hasRole('waiter'))->toBeFalse();

    $registrar->setPermissionsTeamId($hotelB->id);
    $user->unsetRelation('roles');
    expect($user->hasRole('waiter'))->toBeTrue();
    expect($user->hasRole('admin'))->toBeFalse();
});
