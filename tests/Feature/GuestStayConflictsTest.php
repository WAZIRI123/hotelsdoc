<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\AddRoomOccupant;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Domain\Reservations\Actions\NameRoomOccupant;
use App\Livewire\Reservations\Create;
use App\Livewire\Reservations\Show;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->travelTo(CarbonImmutable::parse('2026-07-01 09:00'));

    $this->roomType = RoomType::factory()->for($this->hotel)->create([
        'base_occupancy' => 2,
        'max_occupancy' => 3,
        'max_adults' => 3,
        'max_children' => 1,
    ]);
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '101']);
    $this->otherRoom = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '205']);

    $this->book = fn (Room $room, string $arrival, string $departure, ?Guest $booker = null): Reservation => app(CreateReservation::class)->execute(
        guestId: ($booker ?? Guest::factory()->for($this->hotel)->create())->id,
        roomTypeId: $this->roomType->id,
        roomId: $room->id,
        arrival: CarbonImmutable::parse($arrival),
        departure: CarbonImmutable::parse($departure),
        adults: 2,
    );

    $this->reservation = ($this->book)($this->room, '2026-07-10', '2026-07-13');
    $this->placeholder = $this->reservation->rooms()->first()->occupants()->whereNull('guest_id')->first();

    $this->john = Guest::factory()->for($this->hotel)->create(['first_name' => 'John', 'last_name' => 'Mushi']);
});

/**
 * Puts John into a room on another reservation for the given dates.
 */
function johnStaysIn(Room $room, string $arrival, string $departure): Reservation
{
    $other = (test()->book)($room, $arrival, $departure);
    $other->rooms()->first()->occupants()->whereNull('guest_id')->first()->update(['guest_id' => test()->john->id]);

    return $other;
}

it('refuses to name a guest already staying on another reservation for overlapping dates', function (): void {
    $other = johnStaysIn($this->otherRoom, '2026-07-12', '2026-07-15');

    expect(fn () => app(NameRoomOccupant::class)->execute($this->placeholder, $this->john->id))
        ->toThrow(RuntimeException::class, "John Mushi is already staying in Room 205 ({$other->confirmation_no}) for these dates.");

    expect($this->placeholder->fresh()->guest_id)->toBeNull();
});

it('refuses to add a guest already staying on another reservation for overlapping dates', function (): void {
    johnStaysIn($this->otherRoom, '2026-07-08', '2026-07-11');

    expect(fn () => app(AddRoomOccupant::class)->execute($this->reservation->rooms()->first(), 'adult', $this->john->id))
        ->toThrow(RuntimeException::class, 'John Mushi is already staying in Room 205');
});

it('allows back-to-back stays that do not overlap', function (): void {
    johnStaysIn($this->otherRoom, '2026-07-13', '2026-07-16');

    app(NameRoomOccupant::class)->execute($this->placeholder, $this->john->id);

    expect($this->placeholder->fresh()->guest_id)->toBe($this->john->id);
});

it('ignores reservations that are cancelled or where the guest has already left the room', function (string $how): void {
    $other = johnStaysIn($this->otherRoom, '2026-07-10', '2026-07-13');

    match ($how) {
        'cancelled' => $other->update(['status' => 'cancelled']),
        'left room' => $other->occupants()->where('room_occupants.guest_id', $this->john->id)->update(['checked_out_at' => now()]),
    };

    app(NameRoomOccupant::class)->execute($this->placeholder, $this->john->id);

    expect($this->placeholder->fresh()->guest_id)->toBe($this->john->id);
})->with(['cancelled', 'left room']);

it('warns on the booking form when the booker is already staying elsewhere', function (): void {
    $other = johnStaysIn($this->otherRoom, '2026-07-11', '2026-07-14');

    Livewire::test(Create::class)
        ->dispatch('guest-selected', id: $this->john->id)
        ->set('arrival', '2026-07-12')
        ->set('departure', '2026-07-15')
        ->assertSee("This guest is already staying in Room 205 ({$other->confirmation_no}) for these dates")
        ->set('arrival', '2026-07-14')
        ->assertDontSee('This guest is already staying in');
});

it('flags a booker who is also staying on another reservation, and clears it once replaced', function (): void {
    $other = johnStaysIn($this->otherRoom, '2026-07-11', '2026-07-12');
    $thirdRoom = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '301']);
    $booked = ($this->book)($thirdRoom, '2026-07-10', '2026-07-13', $this->john);
    $booker = $booked->rooms()->first()->occupants()->where('guest_id', $this->john->id)->first();

    Livewire::test(Show::class, ['reservation' => $booked->fresh()])
        ->assertSee("Also in Room 205 ({$other->confirmation_no})")
        ->call('startQuickNaming', $booker->id)
        ->set('quickName', 'Juma Hassan')
        ->call('saveQuickName')
        ->assertHasNoErrors()
        ->assertDontSee('Also in Room 205');

    expect($booked->fresh()->guest_id)->toBe($this->john->id);
});

it('marks suggested guests who are in house elsewhere and refuses picking them', function (): void {
    $other = johnStaysIn($this->otherRoom, '2026-07-11', '2026-07-12');

    Livewire::test(Show::class, ['reservation' => $this->reservation])
        ->call('startQuickNaming', $this->placeholder->id)
        ->set('quickName', 'John Mu')
        ->assertSee("In house: Room 205 ({$other->confirmation_no})")
        ->assertDontSeeHtml('wire:click="pickQuickNameMatch('.$this->john->id.')"')
        ->call('pickQuickNameMatch', $this->john->id);

    expect($this->placeholder->fresh()->guest_id)->toBeNull();
});
