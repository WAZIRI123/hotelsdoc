<?php

declare(strict_types=1);

use App\Livewire\RatePlans\Index as RatePlansIndex;
use App\Livewire\Reservations\Create;
use App\Models\Agent;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
    // Bookings cannot arrive in the past, so the clock starts on the first arrival day used here.
    $this->travelTo(CarbonImmutable::parse('2026-09-01 09:00'));
    $this->roomType = RoomType::factory()->for($this->hotel)->create();
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create();
    $this->guest = Guest::factory()->for($this->hotel)->create();
    $this->agent = Agent::factory()->for($this->hotel)->create();

    $this->rack = RatePlan::factory()->for($this->hotel)->for($this->roomType)->create(['name' => 'RACK BB']);
    $this->sto = RatePlan::factory()->for($this->hotel)->for($this->roomType)->agentRate()->create(['name' => 'STO BB']);
});

it('marks a rate plan as an agent rate', function (): void {
    Livewire::test(RatePlansIndex::class)
        ->call('openCreate')
        ->set('room_type_id', $this->roomType->id)
        ->set('name', 'STO HB')
        ->set('price_tsh', 120)
        ->set('is_agent_rate', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(RatePlan::query()->firstWhere('name', 'STO HB')->is_agent_rate)->toBeTrue();

    Livewire::test(RatePlansIndex::class)
        ->call('openEdit', $this->sto->id)
        ->assertSet('is_agent_rate', true)
        ->set('is_agent_rate', false)
        ->call('save');

    expect($this->sto->fresh()->is_agent_rate)->toBeFalse();
});

it('hides agent rates for walk-in bookings', function (): void {
    Livewire::test(Create::class)
        ->set('room_type_id', $this->roomType->id)
        ->assertSee('RACK BB')
        ->assertDontSee('STO BB');
});

it('shows agent rates first when an agent is the booker', function (): void {
    $component = Livewire::test(Create::class)
        ->set('agent_id', $this->agent->id)
        ->set('room_type_id', $this->roomType->id)
        ->assertSeeInOrder(['STO BB', 'Agent rate', 'RACK BB']);

    expect($component->instance()->availableRatePlans->pluck('name')->all())->toBe(['STO BB', 'RACK BB']);
});

it('clears an agent rate when the booker is switched back to walk-in', function (): void {
    Livewire::test(Create::class)
        ->set('agent_id', $this->agent->id)
        ->set('room_type_id', $this->roomType->id)
        ->set('rate_plan_id', $this->sto->id)
        ->set('agent_id', null)
        ->assertSet('rate_plan_id', null);
});

it('keeps a rack rate when the booker changes', function (): void {
    Livewire::test(Create::class)
        ->set('room_type_id', $this->roomType->id)
        ->set('rate_plan_id', $this->rack->id)
        ->set('agent_id', $this->agent->id)
        ->assertSet('rate_plan_id', $this->rack->id);
});

it('refuses to book an agent rate for a walk-in', function (): void {
    Livewire::test(Create::class)
        ->set('arrival', '2026-09-01')
        ->set('departure', '2026-09-03')
        ->call('selectGuest', $this->guest->id)
        ->set('room_type_id', $this->roomType->id)
        ->set('room_id', $this->room->id)
        ->set('rate_plan_id', $this->sto->id)
        ->call('save')
        ->assertHasErrors('rate_plan_id');

    expect(Reservation::query()->count())->toBe(0);
});

it('books an agent rate when an agent is the booker', function (): void {
    Livewire::test(Create::class)
        ->set('arrival', '2026-09-01')
        ->set('departure', '2026-09-03')
        ->call('selectGuest', $this->guest->id)
        ->set('agent_id', $this->agent->id)
        ->set('room_type_id', $this->roomType->id)
        ->set('room_id', $this->room->id)
        ->set('rate_plan_id', $this->sto->id)
        ->call('save')
        ->assertHasNoErrors();

    $reservation = Reservation::query()->with('rooms')->sole();

    expect($reservation->agent_id)->toBe($this->agent->id)
        ->and($reservation->rooms->first()->rate_plan_id)->toBe($this->sto->id);
});

it('refuses to book a guest or rate plan from another hotel', function (): void {
    $foreignGuest = Guest::factory()->create();
    $foreignPlan = RatePlan::factory()->create();

    Livewire::test(Create::class)
        ->set('guest_id', $foreignGuest->id)
        ->set('room_type_id', $this->roomType->id)
        ->set('room_id', $this->room->id)
        ->set('rate_plan_id', $foreignPlan->id)
        ->call('save')
        ->assertHasErrors(['guest_id', 'rate_plan_id']);

    expect(Reservation::query()->count())->toBe(0);
});
