<?php

declare(strict_types=1);

use App\Domain\Reservations\Actions\CreateReservation;
use App\Models\Guest;
use App\Models\MealPlan;
use App\Models\RateCalendar;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\RoomType;
use App\Support\Money;
use Carbon\CarbonImmutable;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
    $this->action = app(CreateReservation::class);
    $this->roomType = RoomType::factory()->for($this->hotel)->create();
    $this->room = Room::factory()->for($this->hotel)->for($this->roomType)->create(['number' => '101']);
    $this->guest = Guest::factory()->for($this->hotel)->create();
});

it('creates a reservation and blocks availability for every night', function (): void {
    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
    );

    expect($reservation->status)->toBe('confirmed')
        ->and($reservation->rooms()->count())->toBe(1);

    $rows = RoomAvailability::query()
        ->where('reservation_id', $reservation->id)
        ->pluck('date')
        ->map(fn ($d) => $d->toDateString())
        ->sort()
        ->values()
        ->all();

    expect($rows)->toBe(['2026-07-10', '2026-07-11', '2026-07-12']);
});

it('uses the room type default rate when no override is provided', function (): void {
    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-12'),
    );

    expect($reservation->rooms()->first()->nightly_rate->amount)
        ->toBe($this->roomType->default_rate->amount);
});

it('rejects a double booking for the same room and overlapping dates', function (): void {
    $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
    );

    $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-12'),
        departure: CarbonImmutable::parse('2026-07-14'),
    );
})->throws(RuntimeException::class, 'no longer available');

it('rejects when the room does not belong to the given room type', function (): void {
    $otherType = RoomType::factory()->for($this->hotel)->create();

    $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $otherType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-11'),
    );
})->throws(RuntimeException::class, 'does not belong');

it('rejects when adults exceed the room type max_adults', function (): void {
    $this->roomType->update(['max_adults' => 2, 'max_children' => 2, 'max_occupancy' => 4]);

    $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-12'),
        adults: 3,
    );
})->throws(RuntimeException::class, 'at most 2 adults');

it('rejects when total guests exceed max_occupancy', function (): void {
    $this->roomType->update(['max_adults' => 3, 'max_children' => 3, 'max_occupancy' => 3]);

    $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-12'),
        adults: 2,
        children: 2,
    );
})->throws(RuntimeException::class, 'at most 3 guests');

it('adds extra_guest_fee to the nightly rate for guests above base_occupancy', function (): void {
    $this->roomType->update([
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'max_adults' => 4,
        'max_children' => 2,
        'default_rate' => Money::fromMajor(100_000),
        'extra_guest_fee' => Money::fromMajor(20_000),
    ]);

    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-11'),
        adults: 3,
        children: 1,
    );

    expect($reservation->rooms()->first()->nightly_rate->amount)
        ->toBe(Money::fromMajor(140_000)->amount);
});

it('does not add extra_guest_fee when at or below base_occupancy', function (): void {
    $this->roomType->update([
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'default_rate' => Money::fromMajor(100_000),
        'extra_guest_fee' => Money::fromMajor(20_000),
    ]);

    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-11'),
        adults: 2,
        children: 0,
    );

    expect($reservation->rooms()->first()->nightly_rate->amount)
        ->toBe(Money::fromMajor(100_000)->amount);
});

it('uses rate plan price when a rate plan is selected', function (): void {
    $this->roomType->update(['base_occupancy' => 2, 'max_occupancy' => 4, 'max_adults' => 4]);

    $plan = RatePlan::factory()->for($this->hotel)->for($this->roomType)->create([
        'price' => Money::fromMajor(90_000),
        'extra_adult_price' => Money::fromMajor(25_000),
        'extra_child_price' => Money::fromMajor(10_000),
    ]);

    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-11'),
        adults: 2,
        children: 0,
        ratePlanId: $plan->id,
    );

    expect($reservation->rooms()->first()->nightly_rate->amount)
        ->toBe(Money::fromMajor(90_000)->amount);
});

it('adds rate plan extra_adult_price for adults above base_occupancy', function (): void {
    $this->roomType->update(['base_occupancy' => 2, 'max_occupancy' => 4, 'max_adults' => 4]);

    $plan = RatePlan::factory()->for($this->hotel)->for($this->roomType)->create([
        'price' => Money::fromMajor(90_000),
        'extra_adult_price' => Money::fromMajor(25_000),
        'extra_child_price' => Money::fromMajor(10_000),
    ]);

    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-11'),
        adults: 3,
        children: 0,
        ratePlanId: $plan->id,
    );

    // 90k base + 1 extra adult * 25k = 115k
    expect($reservation->rooms()->first()->nightly_rate->amount)
        ->toBe(Money::fromMajor(115_000)->amount);
});

it('adds rate plan extra_child_price for every child', function (): void {
    $this->roomType->update(['base_occupancy' => 2, 'max_occupancy' => 4, 'max_adults' => 4, 'max_children' => 2]);

    $plan = RatePlan::factory()->for($this->hotel)->for($this->roomType)->create([
        'price' => Money::fromMajor(90_000),
        'extra_adult_price' => Money::fromMajor(25_000),
        'extra_child_price' => Money::fromMajor(10_000),
    ]);

    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-11'),
        adults: 2,
        children: 2,
        ratePlanId: $plan->id,
    );

    // 90k base + 2 children * 10k = 110k
    expect($reservation->rooms()->first()->nightly_rate->amount)
        ->toBe(Money::fromMajor(110_000)->amount);
});

it('rate plan pricing ignores room-type extra_guest_fee', function (): void {
    $this->roomType->update([
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'max_adults' => 4,
        'extra_guest_fee' => Money::fromMajor(999_000), // large — should be ignored when rate plan used
    ]);

    $plan = RatePlan::factory()->for($this->hotel)->for($this->roomType)->create([
        'price' => Money::fromMajor(100_000),
        'extra_adult_price' => Money::fromMajor(20_000),
        'extra_child_price' => Money::fromMajor(0),
    ]);

    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-11'),
        adults: 3,
        children: 0,
        ratePlanId: $plan->id,
    );

    // 100k + 1 extra adult * 20k = 120k (NOT 100k + 999k)
    expect($reservation->rooms()->first()->nightly_rate->amount)
        ->toBe(Money::fromMajor(120_000)->amount);
});

it('carries the rate plan meal plan through the reservation_room', function (): void {
    $bb = MealPlan::factory()->for($this->hotel)->breakfast()->create();
    $plan = RatePlan::factory()->for($this->hotel)->for($this->roomType)->create([
        'meal_plan_id' => $bb->id,
        'price' => Money::fromMajor(80_000),
    ]);

    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-11'),
        ratePlanId: $plan->id,
    );

    $resRoom = $reservation->rooms()->with('ratePlan.mealPlan')->first();
    expect($resRoom->rate_plan_id)->toBe($plan->id)
        ->and($resRoom->ratePlan->mealPlan->code)->toBe('BB');
});

it('respects an explicit nightlyRate override and skips extra_guest_fee', function (): void {
    $this->roomType->update([
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'max_adults' => 4,
        'extra_guest_fee' => Money::fromMajor(20_000),
    ]);

    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-11'),
        adults: 4,
        nightlyRate: Money::fromMajor(80_000),
    );

    expect($reservation->rooms()->first()->nightly_rate->amount)
        ->toBe(Money::fromMajor(80_000)->amount);
});

it('snapshots per-night rates from the rate calendar at booking time', function (): void {
    $plan = RatePlan::factory()->for($this->hotel)->for($this->roomType)->create([
        'price' => Money::fromMajor(80_000),
    ]);
    RateCalendar::factory()->for($this->hotel)->for($plan)->create([
        'date' => '2026-07-11',
        'price' => Money::fromMajor(200_000),
    ]);

    $reservation = $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
        ratePlanId: $plan->id,
    );

    $nights = $reservation->rooms()->first()->nights()
        ->orderBy('date')
        ->get()
        ->mapWithKeys(fn ($n) => [$n->date->toDateString() => $n->rate->amount])
        ->all();

    expect($nights)->toBe([
        '2026-07-10' => Money::fromMajor(80_000)->amount,
        '2026-07-11' => Money::fromMajor(200_000)->amount,
        '2026-07-12' => Money::fromMajor(80_000)->amount,
    ]);
});

it('rejects a reservation crossing a stop_sell date', function (): void {
    $plan = RatePlan::factory()->for($this->hotel)->for($this->roomType)->create([
        'price' => Money::fromMajor(80_000),
    ]);
    RateCalendar::factory()->for($this->hotel)->for($plan)->stopSell()->create([
        'date' => '2026-07-11',
    ]);

    $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-12'),
        ratePlanId: $plan->id,
    );
})->throws(RuntimeException::class, 'closed');

it('allows back-to-back bookings (checkout same day as next arrival)', function (): void {
    $this->action->execute(
        guestId: $this->guest->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-10'),
        departure: CarbonImmutable::parse('2026-07-13'),
    );

    $next = $this->action->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $this->roomType->id,
        roomId: $this->room->id,
        arrival: CarbonImmutable::parse('2026-07-13'),
        departure: CarbonImmutable::parse('2026-07-15'),
    );

    expect(Reservation::count())->toBe(2)
        ->and($next->status)->toBe('confirmed');
});
