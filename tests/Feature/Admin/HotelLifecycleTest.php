<?php

declare(strict_types=1);

use App\Livewire\Admin\Hotels\Show;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\PaymentMethod;
use App\Models\RoomType;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('suspends and reactivates a hotel', function (): void {
    $hotel = Hotel::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Show::class, ['hotel' => $hotel])
        ->call('suspend')
        ->assertSee('Suspended');

    expect($hotel->fresh()->isSuspended())->toBeTrue();

    Livewire::test(Show::class, ['hotel' => $hotel->fresh()])->call('reactivate');

    expect($hotel->fresh()->isSuspended())->toBeFalse();
});

it('refuses to delete an active hotel', function (): void {
    $hotel = Hotel::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Show::class, ['hotel' => $hotel])
        ->set('deleteConfirmation', $hotel->slug)
        ->call('deleteHotel')
        ->assertHasErrors('deleteConfirmation');

    expect(Hotel::query()->whereKey($hotel->id)->exists())->toBeTrue();
});

it('requires the slug to be typed before deleting', function (): void {
    $hotel = Hotel::factory()->suspended()->create();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Show::class, ['hotel' => $hotel])
        ->set('deleteConfirmation', 'wrong')
        ->call('deleteHotel')
        ->assertHasErrors('deleteConfirmation');

    expect(Hotel::query()->whereKey($hotel->id)->exists())->toBeTrue();
});

it('deletes a suspended hotel with all its data but keeps user accounts', function (): void {
    $hotel = Hotel::factory()->create();
    $otherHotel = Hotel::factory()->create();
    [, $staff] = actingAsTenantUser($hotel);
    RoomType::factory()->create(['hotel_id' => $hotel->id]);
    Guest::factory()->create(['hotel_id' => $hotel->id]);
    $hotel->forceFill(['suspended_at' => now()])->save();

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Show::class, ['hotel' => $hotel])
        ->set('deleteConfirmation', $hotel->slug)
        ->call('deleteHotel')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.hotels.index'));

    expect(Hotel::query()->whereKey($hotel->id)->exists())->toBeFalse()
        ->and(RoomType::query()->where('hotel_id', $hotel->id)->exists())->toBeFalse()
        ->and(Guest::query()->where('hotel_id', $hotel->id)->exists())->toBeFalse()
        ->and(PaymentMethod::query()->where('hotel_id', $hotel->id)->exists())->toBeFalse()
        ->and(Role::query()->where('hotel_id', $hotel->id)->exists())->toBeFalse()
        ->and(User::query()->whereKey($staff->id)->exists())->toBeTrue()
        ->and(Role::query()->where('hotel_id', $otherHotel->id)->exists())->toBeTrue();
});

it('blocks hotel staff from suspending or deleting hotels', function (): void {
    [$hotel] = actingAsTenantUser(role: 'admin');

    Livewire::test(Show::class, ['hotel' => $hotel])->assertForbidden();
    $this->get(route('admin.hotels.show', $hotel))->assertForbidden();
});
