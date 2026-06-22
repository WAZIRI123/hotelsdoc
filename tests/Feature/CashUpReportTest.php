<?php

declare(strict_types=1);

use App\Domain\Payments\Actions\RecordPayment;
use App\Livewire\Reports\CashUp;
use App\Models\Folio;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
});

it('groups payments by method and computes totals for the selected day', function (): void {
    $folio = Folio::query()->create([
        'hotel_id' => $this->hotel->id,
        'total_amount' => 200_000_00,
        'status' => 'open',
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 10:00:00'));

    app(RecordPayment::class)->execute($folio, 'cash', Money::fromMajor(30_000), receivedBy: $this->user);
    app(RecordPayment::class)->execute($folio, 'cash', Money::fromMajor(20_000), receivedBy: $this->user);
    app(RecordPayment::class)->execute($folio, 'mpesa', Money::fromMajor(40_000), reference: 'M1', receivedBy: $this->user);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-16 10:00:00'));
    app(RecordPayment::class)->execute($folio->fresh(), 'cash', Money::fromMajor(10_000), receivedBy: $this->user);
    CarbonImmutable::setTestNow();

    Livewire::test(CashUp::class, ['date' => '2026-09-15'])
        ->assertSet('date', '2026-09-15')
        ->tap(function ($t): void {
            $totals = $t->instance()->totalsByMethod();
            expect($totals['cash']['count'])->toBe(2)
                ->and($totals['cash']['total']->toMajor())->toBe(50_000.0)
                ->and($totals['mpesa']['count'])->toBe(1)
                ->and($totals['mpesa']['total']->toMajor())->toBe(40_000.0);

            expect($t->instance()->grandTotal()->toMajor())->toBe(90_000.0);
        });
});

it('shows zero totals on a day with no payments', function (): void {
    Livewire::test(CashUp::class, ['date' => '2026-09-15'])
        ->tap(function ($t): void {
            expect($t->instance()->grandTotal()->toMajor())->toBe(0.0)
                ->and($t->instance()->totalsByMethod())->toBe([]);
        });
});
