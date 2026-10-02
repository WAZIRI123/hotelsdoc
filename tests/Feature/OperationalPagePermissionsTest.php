<?php

declare(strict_types=1);

use App\Models\Folio;
use App\Models\Hotel;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\RatePlan;
use App\Models\Reservation;

/**
 * Route parameters for each guarded page, built for the given hotel.
 *
 * @return array<string, Closure(Hotel): array<string, mixed>>
 */
function operationalRouteParameters(): array
{
    return [
        'rate-plans.calendar' => fn (Hotel $hotel): array => ['ratePlan' => RatePlan::factory()->for($hotel)->create()],
        'reservations.show' => fn (Hotel $hotel): array => ['reservation' => Reservation::factory()->for($hotel)->create()],
        'folios.show' => fn (Hotel $hotel): array => ['folio' => Folio::factory()->for($hotel)->create()],
        'folios.print' => fn (Hotel $hotel): array => ['folio' => Folio::factory()->for($hotel)->create()],
        'manage-stock.outlet' => fn (Hotel $hotel): array => ['outlet' => Outlet::factory()->for($hotel)->create()],
        'manage-stock.issue' => fn (Hotel $hotel): array => ['outlet' => Outlet::factory()->for($hotel)->create()],
        'menu.index' => fn (Hotel $hotel): array => ['outlet' => Outlet::factory()->for($hotel)->create()],
        'pos.terminal' => fn (Hotel $hotel): array => ['outlet' => Outlet::factory()->for($hotel)->create()],
        'pos.transactions.show' => fn (Hotel $hotel): array => ['posOrder' => PosOrder::factory()->for($hotel)->for(Outlet::factory()->for($hotel))->create()],
        'pos.transactions.print' => fn (Hotel $hotel): array => ['posOrder' => PosOrder::factory()->for($hotel)->for(Outlet::factory()->for($hotel))->create()],
    ];
}

/**
 * @return array<string, mixed>
 */
function operationalRouteParametersFor(string $routeName, Hotel $hotel): array
{
    $factory = operationalRouteParameters()[$routeName] ?? null;

    return $factory === null ? [] : $factory($hotel);
}

dataset('guarded operational pages', [
    // route name, a role without access, a role with access
    ['room-settings', 'front_desk', 'manager'],
    ['meal-plans.index', 'front_desk', 'manager'],
    ['rate-plans.index', 'front_desk', 'manager'],
    ['rate-plans.calendar', 'front_desk', 'manager'],
    ['rooms.index', 'front_desk', 'manager'],
    ['room-types.index', 'front_desk', 'manager'],
    ['guests.index', 'waiter', 'front_desk'],
    ['guests.register', 'waiter', 'front_desk'],
    ['guests.list', 'waiter', 'front_desk'],
    ['reservations.index', 'cashier', 'front_desk'],
    ['reservations.create', 'cashier', 'front_desk'],
    ['reservations.show', 'cashier', 'front_desk'],
    ['folios.show', 'waiter', 'cashier'],
    ['folios.print', 'waiter', 'cashier'],
    ['manage-stock', 'front_desk', 'accountant'],
    ['manage-stock.outlet', 'front_desk', 'accountant'],
    ['manage-stock.issue', 'front_desk', 'accountant'],
    ['outlets.index', 'accountant', 'manager'],
    ['menu.index', 'accountant', 'manager'],
    ['pos.lobby', 'front_desk', 'waiter'],
    ['pos.terminal', 'front_desk', 'waiter'],
    ['pos.transactions.show', 'front_desk', 'waiter'],
    ['pos.transactions.print', 'front_desk', 'waiter'],
]);

it('forbids staff without the permission from opening the page', function (string $routeName, string $deniedRole): void {
    [$hotel] = actingAsTenantUser(role: $deniedRole);

    $this->get(route($routeName, operationalRouteParametersFor($routeName, $hotel)))->assertForbidden();
})->with('guarded operational pages');

it('lets staff with the permission open the page', function (string $routeName, string $deniedRole, string $allowedRole): void {
    [$hotel] = actingAsTenantUser(role: $allowedRole);

    $this->get(route($routeName, operationalRouteParametersFor($routeName, $hotel)))->assertOk();
})->with('guarded operational pages');

it('hides sidebar links to pages the user cannot open', function (): void {
    actingAsTenantUser(role: 'waiter');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('pos.lobby'))
        ->assertDontSee(route('guests.index'))
        ->assertDontSee(route('room-settings'))
        ->assertDontSee(route('manage-stock'));
});
