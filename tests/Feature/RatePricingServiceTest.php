<?php

declare(strict_types=1);

use App\Domain\Rates\Services\RatePricingService;
use App\Models\RateCalendar;
use App\Models\RatePlan;
use App\Models\RoomType;
use App\Support\Money;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
    $this->service = app(RatePricingService::class);
    $this->roomType = RoomType::factory()->for($this->hotel)->create([
        'base_occupancy' => 2,
        'max_occupancy' => 4,
        'max_adults' => 4,
    ]);
    $this->ratePlan = RatePlan::factory()->for($this->hotel)->for($this->roomType)->create([
        'price' => Money::fromMajor(100_000),
        'extra_adult_price' => Money::fromMajor(25_000),
        'extra_child_price' => Money::fromMajor(10_000),
    ]);
});

it('returns the rate plan base price for every night when no calendar overrides', function (): void {
    $rates = $this->service->resolveNightlyRates(
        $this->ratePlan, $this->roomType, adults: 2, children: 0,
        nights: ['2026-08-10', '2026-08-11', '2026-08-12'],
    );

    expect($rates)->toHaveCount(3)
        ->and($rates['2026-08-10']->amount)->toBe(Money::fromMajor(100_000)->amount)
        ->and($rates['2026-08-11']->amount)->toBe(Money::fromMajor(100_000)->amount)
        ->and($rates['2026-08-12']->amount)->toBe(Money::fromMajor(100_000)->amount);
});

it('uses calendar price override for the specific date', function (): void {
    RateCalendar::factory()->for($this->hotel)->for($this->ratePlan)->create([
        'date' => '2026-08-11',
        'price' => Money::fromMajor(150_000),
    ]);

    $rates = $this->service->resolveNightlyRates(
        $this->ratePlan, $this->roomType, adults: 2, children: 0,
        nights: ['2026-08-10', '2026-08-11', '2026-08-12'],
    );

    expect($rates['2026-08-10']->amount)->toBe(Money::fromMajor(100_000)->amount)
        ->and($rates['2026-08-11']->amount)->toBe(Money::fromMajor(150_000)->amount)
        ->and($rates['2026-08-12']->amount)->toBe(Money::fromMajor(100_000)->amount);
});

it('adds extra-guest surcharges on top of both base and override prices', function (): void {
    RateCalendar::factory()->for($this->hotel)->for($this->ratePlan)->create([
        'date' => '2026-08-11',
        'price' => Money::fromMajor(150_000),
    ]);

    // 3 adults + 1 child → 1 extra adult (25k) + 1 child (10k) = 35k surcharge/night
    $rates = $this->service->resolveNightlyRates(
        $this->ratePlan, $this->roomType, adults: 3, children: 1,
        nights: ['2026-08-10', '2026-08-11'],
    );

    expect($rates['2026-08-10']->amount)->toBe(Money::fromMajor(135_000)->amount)
        ->and($rates['2026-08-11']->amount)->toBe(Money::fromMajor(185_000)->amount);
});

it('rejects a stay that hits a stop_sell date', function (): void {
    RateCalendar::factory()->for($this->hotel)->for($this->ratePlan)->stopSell()->create([
        'date' => '2026-08-11',
    ]);

    $this->service->assertBookable($this->ratePlan, ['2026-08-10', '2026-08-11']);
})->throws(RuntimeException::class, 'closed');

it('rejects a stay shorter than min_stay set on the arrival date', function (): void {
    RateCalendar::factory()->for($this->hotel)->for($this->ratePlan)->create([
        'date' => '2026-08-10',
        'min_stay' => 3,
    ]);

    $this->service->assertBookable($this->ratePlan, ['2026-08-10', '2026-08-11']);
})->throws(RuntimeException::class, 'minimum stay of 3');

it('allows a stay that meets min_stay', function (): void {
    RateCalendar::factory()->for($this->hotel)->for($this->ratePlan)->create([
        'date' => '2026-08-10',
        'min_stay' => 2,
    ]);

    $this->service->assertBookable($this->ratePlan, ['2026-08-10', '2026-08-11']);

    expect(true)->toBeTrue();
});

it('does not enforce min_stay when only set on a mid-stay date', function (): void {
    RateCalendar::factory()->for($this->hotel)->for($this->ratePlan)->create([
        'date' => '2026-08-11',
        'min_stay' => 5,
    ]);

    $this->service->assertBookable($this->ratePlan, ['2026-08-10', '2026-08-11']);

    expect(true)->toBeTrue();
});
