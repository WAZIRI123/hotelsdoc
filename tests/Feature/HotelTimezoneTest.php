<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\Hotel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

it('runs on the active hotel\'s clock', function (): void {
    $hotel = Hotel::factory()->create(['timezone' => 'Africa/Dar_es_Salaam']);

    // 22:30 UTC is already 01:30 the next morning in Dar es Salaam (UTC+3).
    $this->travelTo(CarbonImmutable::parse('2026-11-10 22:30', 'UTC'));
    app(TenantContext::class)->set($hotel);

    expect(date_default_timezone_get())->toBe('Africa/Dar_es_Salaam')
        ->and(config('app.timezone'))->toBe('Africa/Dar_es_Salaam')
        ->and(today()->toDateString())->toBe('2026-11-11')
        ->and(now()->format('H:i'))->toBe('01:30');

    app(TenantContext::class)->clear();

    expect(date_default_timezone_get())->toBe('UTC');
});

it('switches to the hotel timezone for each request', function (): void {
    [$hotel] = actingAsTenantUser(Hotel::factory()->create(['timezone' => 'Asia/Dubai']));
    app(TenantContext::class)->clear();

    $this->get(route('dashboard'))->assertOk();

    expect(date_default_timezone_get())->toBe('Asia/Dubai')
        ->and(app(TenantContext::class)->id())->toBe($hotel->id);
});

it('shifts timestamps recorded in UTC onto each hotel\'s clock', function (): void {
    $dar = Hotel::factory()->create(['timezone' => 'Africa/Dar_es_Salaam']);
    $utc = Hotel::factory()->create(['timezone' => 'UTC']);

    foreach ([$dar, $utc] as $hotel) {
        DB::table('guests')->insert([
            'hotel_id' => $hotel->id,
            'uuid' => (string) str()->uuid(),
            'first_name' => 'Guest '.$hotel->id,
            'created_at' => '2026-09-30 22:15:00',
            'updated_at' => '2026-09-30 22:15:00',
        ]);
    }

    $migration = require database_path('migrations/2026_10_03_061625_convert_tenant_timestamps_to_hotel_timezone.php');
    $migration->up();

    $createdAt = fn (Hotel $hotel): string => (string) DB::table('guests')->where('hotel_id', $hotel->id)->value('created_at');

    expect($createdAt($dar))->toBe('2026-10-01 01:15:00')
        ->and($createdAt($utc))->toBe('2026-09-30 22:15:00');

    $migration->down();

    expect($createdAt($dar))->toBe('2026-09-30 22:15:00');
});
