<?php

declare(strict_types=1);

use App\Domain\Tenancy\HotelAccess;
use App\Domain\Tenancy\TenantContext;
use App\Models\Hotel;
use App\Models\User;

/**
 * @return array{0: User, 1: Hotel, 2: Hotel}
 */
function userInTwoHotels(): array
{
    $alpha = Hotel::factory()->create(['name' => 'Alpha Lodge']);
    $beta = Hotel::factory()->create(['name' => 'Beta Inn']);
    [, $user] = actingAsTenantUser($alpha);
    actingAsTenantUser($beta, $user);

    return [$user, $alpha, $beta];
}

it('opens the first active hotel by name when nothing is chosen', function (): void {
    [, $alpha] = userInTwoHotels();

    $this->get(route('dashboard'))->assertOk();

    expect(app(TenantContext::class)->id())->toBe($alpha->id);
});

it('switches to another hotel the user belongs to', function (): void {
    [, , $beta] = userInTwoHotels();

    $this->post(route('hotels.switch', $beta))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(HotelAccess::SESSION_KEY, $beta->id);

    $this->get(route('dashboard'))->assertOk()->assertSee('Beta Inn');
    expect(app(TenantContext::class)->id())->toBe($beta->id);
});

it('refuses to switch to a hotel the user does not belong to', function (): void {
    userInTwoHotels();
    $stranger = Hotel::factory()->create();

    $this->post(route('hotels.switch', $stranger))->assertForbidden();
    $this->assertFalse(session()->get(HotelAccess::SESSION_KEY) === $stranger->id);
});

it('falls back to another hotel when removed from the chosen one', function (): void {
    [$user, $alpha, $beta] = userInTwoHotels();

    $this->post(route('hotels.switch', $beta));
    $beta->users()->detach($user->id);

    $this->get(route('dashboard'))->assertOk();
    expect(app(TenantContext::class)->id())->toBe($alpha->id);
});

it('blocks staff from a suspended hotel', function (): void {
    $hotel = Hotel::factory()->create();
    actingAsTenantUser($hotel);
    $hotel->forceFill(['suspended_at' => now()])->save();

    $this->get(route('dashboard'))
        ->assertForbidden()
        ->assertSee('This hotel has been suspended');
});

it('moves staff to their other hotel when the chosen one is suspended', function (): void {
    [, $alpha, $beta] = userInTwoHotels();

    $this->post(route('hotels.switch', $beta));
    $beta->forceFill(['suspended_at' => now()])->save();

    $this->get(route('dashboard'))->assertOk();
    expect(app(TenantContext::class)->id())->toBe($alpha->id);

    $this->post(route('hotels.switch', $beta))->assertForbidden();
});

it('lets a super-admin enter any hotel with full permissions', function (): void {
    $hotel = Hotel::factory()->suspended()->create(['name' => 'Remote Resort']);
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->post(route('hotels.switch', $hotel))->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertOk()->assertSee('Remote Resort')->assertSee('Visiting');
    $this->get(route('users.index'))->assertOk();
    expect(app(TenantContext::class)->id())->toBe($hotel->id);
});

it('does not give hotel staff permissions their role lacks', function (): void {
    actingAsTenantUser(role: 'front_desk');

    $this->get(route('users.index'))->assertForbidden();
});
