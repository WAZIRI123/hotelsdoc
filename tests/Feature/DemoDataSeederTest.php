<?php

declare(strict_types=1);

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

test('the demo seeder books past, in-house and upcoming stays', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(Reservation::query()->withoutGlobalScopes()->where('status', 'checked_out')->count())->toBe(4)
        ->and(Reservation::query()->withoutGlobalScopes()->where('status', 'checked_in')->count())->toBe(3)
        ->and(CarbonImmutable::now()->isToday())->toBeTrue();
});
