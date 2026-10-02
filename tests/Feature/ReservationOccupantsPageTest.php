<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CreateReservation;
use App\Livewire\Reservations\Show;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->travelTo(CarbonImmutable::parse('2026-07-01 09:00'));

    $this->roomType = RoomType::factory()->for($this->hotel)->create([
        'name' => 'Deluxe',
        'base_occupancy' => 2,
        'max_occupancy' => 3,
        'max_adults' => 3,
        'max_children' => 1,
    ]);
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '101']);
    $this->spare = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '102']);
    $this->guest = Guest::factory()->for($this->hotel)->create(['first_name' => 'Asha', 'last_name' => 'Mrisho']);

    $this->reservation = app(CreateReservation::class)->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
        adults: 2,
    );
    $this->reservationRoom = $this->reservation->rooms()->first();
});

it('lists the people in each room', function (): void {
    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->assertSee('Asha Mrisho')
        ->assertSee('Primary')
        ->assertSee('Unnamed adult')
        ->assertSee('Add person')
        ->assertSee('Move room');
});

it('adds a named person to a room', function (): void {
    $companion = Guest::factory()->for($this->hotel)->create(['first_name' => 'Baraka']);

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('startAddingOccupant', $this->reservationRoom->id)
        ->dispatch('guest-selected', id: $companion->id)
        ->call('saveOccupant')
        ->assertHasNoErrors()
        ->assertSee('Baraka');

    expect($this->reservationRoom->fresh()->adults)->toBe(3);
});

it('names an unnamed placeholder', function (): void {
    $companion = Guest::factory()->for($this->hotel)->create(['first_name' => 'Neema']);
    $placeholder = $this->reservationRoom->occupants()->whereNull('guest_id')->first();

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('startNamingOccupant', $placeholder->id)
        ->call('saveOccupant')
        ->assertHasErrors(['occupantGuestId'])
        ->dispatch('guest-selected', id: $companion->id)
        ->call('saveOccupant')
        ->assertHasNoErrors()
        ->assertSee('Neema');

    expect($placeholder->fresh()->guest_id)->toBe($companion->id);
});

it('quick-names a placeholder by typing, registering a name-only guest', function (): void {
    $placeholder = $this->reservationRoom->occupants()->whereNull('guest_id')->first();

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('startQuickNaming', $placeholder->id)
        ->call('saveQuickName')
        ->assertHasErrors(['quickName' => 'required'])
        ->set('quickName', '  John   Mushi Juma ')
        ->call('saveQuickName')
        ->assertHasNoErrors()
        ->assertSet('quickNamingOccupantId', null)
        ->assertSee('John Mushi Juma')
        ->assertSee('ID missing');

    $guest = $placeholder->fresh()->guest;
    expect($guest)->not->toBeNull()
        ->and($guest->first_name)->toBe('John')
        ->and($guest->last_name)->toBe('Mushi Juma')
        ->and($guest->hotel_id)->toBe($this->hotel->id)
        ->and($guest->id_number)->toBeNull();
});

it('suggests existing guests while typing and links the picked one', function (): void {
    $existing = Guest::factory()->for($this->hotel)->create(['first_name' => 'Neema', 'last_name' => 'Kweka']);
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Zawadi', 'last_name' => 'Kweka']);
    $placeholder = $this->reservationRoom->occupants()->whereNull('guest_id')->first();
    $guestsBefore = Guest::query()->count();

    $component = Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('startQuickNaming', $placeholder->id)
        ->set('quickName', 'nee kwe');

    expect($component->instance()->quickNameMatches->pluck('id')->all())->toBe([$existing->id]);

    $component->assertSee('Already registered?')
        ->call('pickQuickNameMatch', $existing->id)
        ->assertHasNoErrors();

    expect($placeholder->fresh()->guest_id)->toBe($existing->id)
        ->and(Guest::query()->count())->toBe($guestsBefore);
});

it('refuses to quick-name with a guest already in the reservation', function (): void {
    $placeholder = $this->reservationRoom->occupants()->whereNull('guest_id')->first();

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('startQuickNaming', $placeholder->id)
        ->call('pickQuickNameMatch', $this->guest->id);

    expect($placeholder->fresh()->guest_id)->toBeNull();
});

it('fixes a typo by renaming the quick-named guest in place', function (): void {
    $placeholder = $this->reservationRoom->occupants()->whereNull('guest_id')->first();

    $component = Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('startQuickNaming', $placeholder->id)
        ->set('quickName', 'Jhon Mushi')
        ->call('saveQuickName');

    $guestId = $placeholder->fresh()->guest_id;
    $guestsBefore = Guest::query()->count();

    $component->assertSeeHtml('wire:click="startQuickNaming('.$placeholder->id.')"')
        ->call('startQuickNaming', $placeholder->id)
        ->assertSet('quickName', 'Jhon Mushi')
        ->set('quickName', 'John Mushi')
        ->call('saveQuickName')
        ->assertHasNoErrors()
        ->assertSee('John Mushi')
        ->assertDontSee('Jhon Mushi');

    expect($placeholder->fresh()->guest_id)->toBe($guestId)
        ->and(Guest::query()->find($guestId)->fullName())->toBe('John Mushi')
        ->and(Guest::query()->count())->toBe($guestsBefore);
});

it('discards the typo guest when an existing guest is picked instead', function (): void {
    $existing = Guest::factory()->for($this->hotel)->create(['first_name' => 'Neema', 'last_name' => 'Kweka']);
    $placeholder = $this->reservationRoom->occupants()->whereNull('guest_id')->first();

    $component = Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('startQuickNaming', $placeholder->id)
        ->set('quickName', 'Nema Kweka')
        ->call('saveQuickName');

    $typoGuestId = $placeholder->fresh()->guest_id;

    $component->call('startQuickNaming', $placeholder->id)
        ->set('quickName', 'Kweka')
        ->call('pickQuickNameMatch', $existing->id)
        ->assertHasNoErrors();

    expect($placeholder->fresh()->guest_id)->toBe($existing->id)
        ->and(Guest::query()->find($typoGuestId))->toBeNull();
});

it('never renames a guest that has other history', function (): void {
    $regular = Guest::factory()->for($this->hotel)->create(['first_name' => 'Neema', 'last_name' => 'Kweka']);
    Reservation::factory()->for($this->hotel)->create(['guest_id' => $regular->id]);
    $placeholder = $this->reservationRoom->occupants()->whereNull('guest_id')->first();
    $placeholder->update(['guest_id' => $regular->id]);

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('startQuickNaming', $placeholder->id)
        ->set('quickName', 'Zawadi Kweka')
        ->call('saveQuickName')
        ->assertHasNoErrors();

    expect($regular->fresh()->fullName())->toBe('Neema Kweka')
        ->and($placeholder->fresh()->guest->fullName())->toBe('Zawadi Kweka');
});

it('replaces a booker who is not staying without renaming the booker', function (): void {
    $booker = $this->reservationRoom->occupants()->where('guest_id', $this->guest->id)->first();

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->assertSee('Not staying')
        ->call('startQuickNaming', $booker->id)
        ->assertSet('quickNamingOccupantId', $booker->id)
        ->assertSet('quickName', '')
        ->set('quickName', 'Juma Hassan')
        ->call('saveQuickName')
        ->assertHasNoErrors();

    expect($this->guest->fresh()->fullName())->toBe('Asha Mrisho')
        ->and($this->reservation->fresh()->guest_id)->toBe($this->guest->id)
        ->and($booker->fresh()->guest->fullName())->toBe('Juma Hassan')
        ->and($booker->fresh()->is_primary)->toBeTrue();
});

it('still allows naming on the departure day of a checked-in stay', function (): void {
    $this->reservation->update(['status' => 'checked_in']);
    $this->travelTo(CarbonImmutable::parse('2026-07-13 08:00'));
    $placeholder = $this->reservationRoom->occupants()->whereNull('guest_id')->first();

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->assertSeeHtml('wire:click="startQuickNaming('.$placeholder->id.')"')
        ->assertDontSeeHtml('wire:click="startTransfer')
        ->call('startQuickNaming', $placeholder->id)
        ->set('quickName', 'Baraka Said')
        ->call('saveQuickName')
        ->assertHasNoErrors();

    expect($placeholder->fresh()->guest?->fullName())->toBe('Baraka Said');
});

it('blocks naming once the reservation is cancelled', function (): void {
    $this->reservation->update(['status' => 'cancelled']);
    $placeholder = $this->reservationRoom->occupants()->whereNull('guest_id')->first();

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->assertDontSeeHtml('wire:click="startQuickNaming')
        ->call('startQuickNaming', $placeholder->id)
        ->assertSet('quickNamingOccupantId', null)
        ->set('quickNamingOccupantId', $placeholder->id)
        ->set('quickName', 'Sneaky Name')
        ->call('saveQuickName');

    expect($placeholder->fresh()->guest_id)->toBeNull();
});

it('hides the ID missing badge once the guest has an ID number', function (): void {
    $this->guest->update(['id_type' => 'passport', 'id_number' => 'TZ123456']);

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->assertSee('Asha Mrisho')
        ->assertDontSee('ID missing');
});

it('removes a person from a room', function (): void {
    $placeholder = $this->reservationRoom->occupants()->whereNull('guest_id')->first();

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('removeOccupant', $placeholder->id)
        ->assertDontSee('Unnamed adult');

    expect($this->reservationRoom->fresh()->adults)->toBe(1);
});

it('moves the whole room', function (): void {
    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('startMovingRoom', $this->reservationRoom->id)
        ->assertSee('102 — Deluxe')
        ->set('moveToRoomId', $this->spare->id)
        ->set('moveReason', 'Guest request')
        ->call('moveRoom')
        ->assertHasNoErrors()
        ->assertSee('Moved 101 → 102');

    expect($this->reservationRoom->fresh()->room_id)->toBe($this->spare->id);
});

it('transfers one person into a free room', function (): void {
    $placeholder = $this->reservationRoom->occupants()->whereNull('guest_id')->first();

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('startTransfer', $placeholder->id)
        ->set('transferTarget', 'room:'.$this->spare->id)
        ->call('transferOccupant')
        ->assertHasNoErrors();

    $newRoom = ReservationRoom::query()->where('room_id', $this->spare->id)->first();
    expect($newRoom)->not->toBeNull()
        ->and($placeholder->fresh()->reservation_room_id)->toBe($newRoom->id);
});

it('cannot act on occupants of another reservation', function (): void {
    $other = Reservation::factory()->for($this->hotel)->create();
    $otherRoom = $other->rooms()->create([
        'room_type_id' => $this->roomType->id,
        'room_id' => $this->spare->id,
        'nightly_rate' => 0,
    ]);
    $stranger = $otherRoom->occupants()->create(['age_category' => 'adult']);

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('removeOccupant', $stranger->id);

    expect($stranger->fresh())->not->toBeNull();
});

it('hides occupancy controls on a cancelled reservation', function (): void {
    $this->reservation->update(['status' => 'cancelled']);

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->assertDontSeeHtml('wire:click="startAddingOccupant')
        ->assertDontSeeHtml('wire:click="startMovingRoom')
        ->assertDontSeeHtml('wire:click="startTransfer');
});
