<?php

declare(strict_types=1);

use App\Livewire\Reservations\Create;
use App\Livewire\Reservations\Show;
use App\Models\Agent;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    // Bookings cannot arrive in the past, so the clock starts on the first arrival day used here.
    $this->travelTo(CarbonImmutable::parse('2026-09-01 09:00'));
    $this->roomType = RoomType::factory()->for($this->hotel)->create(['default_rate' => 120_000_00]);
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '301']);
    $this->guest = Guest::factory()->for($this->hotel)->create();
});

it('creates a reservation via the Livewire form', function (): void {
    Livewire::test(Create::class)
        ->set('arrival', '2026-09-01')
        ->set('departure', '2026-09-04')
        ->call('selectGuest', $this->guest->id)
        ->set('room_type_id', $this->roomType->id)
        ->set('room_id', $this->room->id)
        ->call('save')
        ->assertHasNoErrors();

    $reservation = Reservation::query()->where('guest_id', $this->guest->id)->first();

    expect($reservation)->not->toBeNull()
        ->and($reservation->agent_id)->toBeNull()
        ->and($reservation->source)->toBe('walk_in');
});

it('lists active agents in the Booker field with Walk-in as default', function (): void {
    Agent::factory()->for($this->hotel)->create(['name' => 'Safari Tours Ltd']);
    Agent::factory()->for($this->hotel)->inactive()->create(['name' => 'Retired Travel']);

    Livewire::test(Create::class)
        ->assertSet('agent_id', null)
        ->assertSee('Booker')
        ->assertSee('Walk-in')
        ->assertSee('Special request')
        ->assertDontSee('Notes')
        ->assertSee('Safari Tours Ltd')
        ->assertDontSee('Retired Travel');
});

it('labels the rate plan field as Rate plan (Meal Plan)', function (): void {
    RatePlan::factory()->for($this->hotel)->for($this->roomType)->create(['name' => 'STO']);

    Livewire::test(Create::class)
        ->set('room_type_id', $this->roomType->id)
        ->assertSee('Rate plan (Meal Plan)')
        ->assertSee('STO');
});

it('records the booking agent on the reservation', function (): void {
    $agent = Agent::factory()->for($this->hotel)->create(['name' => 'Safari Tours Ltd']);

    Livewire::test(Create::class)
        ->set('arrival', '2026-09-01')
        ->set('departure', '2026-09-04')
        ->call('selectGuest', $this->guest->id)
        ->set('agent_id', $agent->id)
        ->set('room_type_id', $this->roomType->id)
        ->set('room_id', $this->room->id)
        ->call('save')
        ->assertHasNoErrors();

    $reservation = Reservation::query()->where('guest_id', $this->guest->id)->first();

    expect($reservation->agent_id)->toBe($agent->id)
        ->and($reservation->source)->toBe('agent');

    Livewire::test(Show::class, ['reservation' => $reservation])
        ->assertSee('Booker')
        ->assertSee('Safari Tours Ltd');
});

it('rejects a booker from another hotel or an inactive agent', function (): void {
    $foreign = Agent::factory()->for(Hotel::factory())->create();
    $inactive = Agent::factory()->for($this->hotel)->inactive()->create();

    foreach ([$foreign, $inactive] as $agent) {
        Livewire::test(Create::class)
            ->set('arrival', '2026-09-01')
            ->set('departure', '2026-09-04')
            ->call('selectGuest', $this->guest->id)
            ->set('agent_id', $agent->id)
            ->set('room_type_id', $this->roomType->id)
            ->set('room_id', $this->room->id)
            ->call('save')
            ->assertHasErrors('agent_id');
    }
});

it('falls back to walk-in when the booking agent is deleted', function (): void {
    $agent = Agent::factory()->for($this->hotel)->create();
    $reservation = Reservation::factory()->for($this->hotel)->for($this->guest)->create(['agent_id' => $agent->id]);

    $agent->delete();

    expect($reservation->fresh()->agent_id)->toBeNull();
});

it('only lists available rooms in the Create form', function (): void {
    $otherRoom = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '302']);

    Livewire::test(Create::class)
        ->set('arrival', '2026-09-01')
        ->set('departure', '2026-09-04')
        ->set('room_type_id', $this->roomType->id)
        ->assertSee('Room 301')
        ->assertSee('Room 302');
});

it('filters guests via the guest-picker child component', function (): void {
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Alice', 'last_name' => 'Smith']);
    Guest::factory()->for($this->hotel)->create(['first_name' => 'Bob', 'last_name' => 'Jones']);

    Livewire::test('reservations.guest-picker')
        ->set('search', 'Alice')
        ->assertSee('Alice')
        ->assertDontSee('Bob');
});

it('picker dispatches guest-selected when a guest is clicked', function (): void {
    Livewire::test('reservations.guest-picker')
        ->call('select', $this->guest->id)
        ->assertSet('selectedGuestId', $this->guest->id)
        ->assertDispatched('guest-selected');
});

it('creates a guest inline and dispatches guest-created', function (): void {
    Livewire::test('reservations.new-guest-form')
        ->call('open', 'Brand')
        ->assertSet('show', true)
        ->assertSet('guest_names', 'Brand')
        ->set('guest_names', 'Brand New Customer')
        ->set('phone', '+255 712 999 999')
        ->set('nationality', 'Tanzania')
        ->set('id_type', 'passport')
        ->set('id_number', 'TZ123456')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('show', false)
        ->assertSet('guest_names', '')
        ->assertDispatched('guest-created');

    $guest = Guest::query()->where('first_name', 'Brand')->first();
    expect($guest)->not->toBeNull()
        ->and($guest->last_name)->toBe('New Customer')
        ->and($guest->nationality)->toBe('Tanzania')
        ->and($guest->id_number)->toBe('TZ123456')
        ->and($guest->hotel_id)->toBe($this->hotel->id);
});

it('rejects an inline guest with no names', function (): void {
    Livewire::test('reservations.new-guest-form')
        ->call('open')
        ->call('save')
        ->assertHasErrors('guest_names');
});

it('rejects an inline guest with an unknown nationality', function (): void {
    Livewire::test('reservations.new-guest-form')
        ->call('open', 'Jane Doe')
        ->set('nationality', 'Atlantis')
        ->call('save')
        ->assertHasErrors('nationality');
});

it('selects the guest on the parent when guest-selected is dispatched', function (): void {
    $guest = Guest::factory()->for($this->hotel)->create();

    Livewire::test(Create::class)
        ->dispatch('guest-selected', id: $guest->id)
        ->assertSet('guest_id', $guest->id);
});

it('checks a guest in from the Show page and creates a folio', function (): void {
    $reservation = Reservation::factory()->for($this->hotel)->for($this->guest)->create([
        'arrival' => CarbonImmutable::parse('2026-09-01'),
        'departure' => CarbonImmutable::parse('2026-09-04'),
        'status' => 'confirmed',
    ]);
    $reservation->rooms()->create([
        'hotel_id' => $this->hotel->id,
        'room_type_id' => $this->roomType->id,
        'room_id' => $this->room->id,
        'nightly_rate' => $this->roomType->default_rate,
    ]);

    Livewire::test(Show::class, ['reservation' => $reservation])
        ->call('checkIn');

    $reservation->refresh();
    expect($reservation->status)->toBe('checked_in')
        ->and($reservation->stay)->not->toBeNull()
        ->and($reservation->stay->folio)->not->toBeNull();
});

it('refuses a booking that arrives in the past', function (): void {
    Livewire::test(Create::class)
        ->set('arrival', '2026-08-31')
        ->set('departure', '2026-09-02')
        ->call('selectGuest', $this->guest->id)
        ->set('room_type_id', $this->roomType->id)
        ->set('room_id', $this->room->id)
        ->call('save')
        ->assertHasErrors(['arrival' => 'after_or_equal']);

    expect(Reservation::query()->count())->toBe(0);
});
