<?php

declare(strict_types=1);

use App\Models\Hotel;
use App\Models\PaymentMethod;

it('seeds default payment methods when a hotel is created', function (): void {
    $hotel = Hotel::factory()->create();

    $codes = PaymentMethod::query()
        ->where('hotel_id', $hotel->id)
        ->orderBy('sort_order')
        ->pluck('code')
        ->all();

    expect($codes)->toBe([
        'cash', 'mpesa', 'tigopesa', 'airtelmoney', 'halopesa', 'crdb', 'nmb',
    ]);
});

it('is idempotent across re-runs', function (): void {
    $hotel = Hotel::factory()->create();

    app(\App\Domain\Tenancy\Listeners\SeedDefaultHotelData::class)->handle($hotel);
    app(\App\Domain\Tenancy\Listeners\SeedDefaultHotelData::class)->handle($hotel);

    expect(PaymentMethod::query()->where('hotel_id', $hotel->id)->count())->toBe(7);
});
