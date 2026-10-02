<?php

declare(strict_types=1);

use App\Livewire\Admin\Hotels\Create;
use App\Livewire\Admin\Hotels\Index;
use App\Livewire\Admin\Hotels\Show;
use App\Models\Expense;
use App\Models\Hotel;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

it('redirects guests away from the platform admin', function (): void {
    $this->get(route('admin.hotels.index'))->assertRedirect(route('login'));
});

it('forbids hotel staff from the platform admin', function (): void {
    actingAsTenantUser(role: 'admin');

    $this->get(route('admin.hotels.index'))->assertForbidden();
    $this->get(route('admin.hotels.create'))->assertForbidden();
    Livewire::test(Create::class)->assertForbidden();
});

it('lists every hotel for a super-admin', function (): void {
    Hotel::factory()->create(['name' => 'Alpha Lodge']);
    Hotel::factory()->create(['name' => 'Beta Inn']);

    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get(route('admin.hotels.index'))
        ->assertOk()
        ->assertSee('Alpha Lodge')
        ->assertSee('Beta Inn');

    Livewire::test(Index::class)
        ->set('search', 'beta')
        ->assertSee('Beta Inn')
        ->assertDontSee('Alpha Lodge');
});

it('sends a super-admin without a hotel to the platform admin instead of a 403', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get(route('dashboard'))->assertRedirect(route('admin.organization'));
});

it('provisions a hotel with defaults and a new admin user', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Create::class)
        ->set('name', 'Serengeti View')
        ->assertSet('slug', 'serengeti-view')
        ->set('currency', 'USD')
        ->set('timezone', 'Africa/Nairobi')
        ->set('adminEmail', 'owner@serengeti.test')
        ->set('adminName', 'Owner Person')
        ->set('adminPassword', 'secret-pass')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $hotel = Hotel::query()->where('slug', 'serengeti-view')->firstOrFail();
    expect($hotel->currency)->toBe('USD')
        ->and($hotel->timezone)->toBe('Africa/Nairobi')
        ->and(PaymentMethod::query()->where('hotel_id', $hotel->id)->count())->toBeGreaterThan(0);

    $admin = User::query()->where('email', 'owner@serengeti.test')->firstOrFail();
    expect($admin->is_super_admin)->toBeFalse()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('secret-pass', $admin->password))->toBeTrue()
        ->and($hotel->users()->whereKey($admin->id)->exists())->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId($hotel->id);
    expect($admin->fresh()->hasRole('admin'))->toBeTrue();
});

it('attaches an existing user as admin without changing their password', function (): void {
    $existing = User::factory()->create(['email' => 'multi@hotels.test', 'password' => Hash::make('original-pass')]);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Create::class)
        ->set('name', 'Second Property')
        ->set('adminEmail', 'multi@hotels.test')
        ->call('save')
        ->assertHasNoErrors();

    $hotel = Hotel::query()->where('slug', 'second-property')->firstOrFail();
    expect($hotel->users()->whereKey($existing->id)->exists())->toBeTrue()
        ->and(Hash::check('original-pass', $existing->fresh()->password))->toBeTrue()
        ->and(User::query()->where('email', 'multi@hotels.test')->count())->toBe(1);
});

it('validates the hotel setup form', function (): void {
    Hotel::factory()->create(['slug' => 'taken']);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Create::class)
        ->set('name', 'Taken')
        ->set('slug', 'taken')
        ->set('timezone', 'Not/AZone')
        ->set('adminEmail', 'new@admin.test')
        ->call('save')
        ->assertHasErrors(['slug', 'timezone', 'adminName', 'adminPassword']);

    expect(Hotel::query()->count())->toBe(1);
});

it('updates hotel details from the manage page', function (): void {
    $hotel = Hotel::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get(route('admin.hotels.show', $hotel))->assertOk()->assertSee($hotel->name);

    Livewire::test(Show::class, ['hotel' => $hotel])
        ->set('name', 'Renamed Hotel')
        ->set('vrn', '40-012345-A')
        ->set('phone', '')
        ->call('saveDetails')
        ->assertHasNoErrors();

    $hotel->refresh();
    expect($hotel->name)->toBe('Renamed Hotel')
        ->and($hotel->vrn)->toBe('40-012345-A')
        ->and($hotel->phone)->toBeNull();
});

it('locks the hotel currency once money has been recorded', function (): void {
    $hotel = Hotel::factory()->create(['currency' => 'TZS']);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Show::class, ['hotel' => $hotel])
        ->set('currency', 'USD')
        ->call('saveDetails')
        ->assertHasNoErrors();

    expect($hotel->fresh()->currency)->toBe('USD');

    Expense::factory()->for($hotel)->create(['currency' => 'USD']);

    Livewire::test(Show::class, ['hotel' => $hotel->fresh()])
        ->assertSet('currency', 'USD')
        ->set('currency', 'TZS')
        ->call('saveDetails')
        ->assertHasErrors('currency');

    expect($hotel->fresh()->currency)->toBe('USD');
});

it('adds, re-roles and removes hotel users from the manage page', function (): void {
    $hotel = Hotel::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create());

    $component = Livewire::test(Show::class, ['hotel' => $hotel])
        ->call('openCreateUser')
        ->set('userName', 'Desk Person')
        ->set('userEmail', 'desk@hotel.test')
        ->set('userPassword', 'password123')
        ->set('userRole', 'front_desk')
        ->call('saveUser')
        ->assertHasNoErrors()
        ->assertSee('Desk Person');

    $user = User::query()->where('email', 'desk@hotel.test')->firstOrFail();
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($hotel->id);
    expect($user->fresh()->hasRole('front_desk'))->toBeTrue();

    $component->call('openEditUser', $user->id)
        ->assertSet('userRole', 'front_desk')
        ->set('userRole', 'manager')
        ->call('saveUser')
        ->assertHasNoErrors();

    $registrar->setPermissionsTeamId($hotel->id);
    expect($user->fresh()->hasRole('manager'))->toBeTrue();

    $component->call('removeUser', $user->id);
    expect($hotel->users()->whereKey($user->id)->exists())->toBeFalse();
});

it('promotes a user to super-admin from the console', function (): void {
    $user = User::factory()->create(['email' => 'ops@platform.test']);

    $this->artisan('app:make-super-admin', ['email' => 'ops@platform.test'])->assertSuccessful();
    expect($user->fresh()->is_super_admin)->toBeTrue();

    $this->artisan('app:make-super-admin', ['email' => 'ops@platform.test', '--revoke' => true])->assertSuccessful();
    expect($user->fresh()->is_super_admin)->toBeFalse();
});

it('creates a new super-admin user from the console', function (): void {
    $this->artisan('app:make-super-admin', [
        'email' => 'root@platform.test',
        '--name' => 'Platform Root',
        '--password' => 'long-password',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'root@platform.test')->firstOrFail();
    expect($user->is_super_admin)->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull();
});

it('shows the organization dashboard to a super-admin', function (): void {
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get(route('admin.organization'))
        ->assertOk()
        ->assertSee('Organization Dashboard')
        ->assertSee('System Control')
        ->assertSee('View Details')
        ->assertDontSee('Add Currency')
        ->assertDontSee('View Currency')
        ->assertSee('Add Invoice Footer')
        ->assertSee('View Invoice Footer')
        ->assertSee(route('admin.hotels.index'));
});

it('forbids hotel staff from the organization dashboard', function (): void {
    actingAsTenantUser(role: 'admin');

    $this->get(route('admin.organization'))->assertForbidden();
});
