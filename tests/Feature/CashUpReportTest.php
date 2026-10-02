<?php

declare(strict_types=1);

use App\Domain\Folios\Actions\PostFolioCharge;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Livewire\Reports\CashUp;
use App\Models\Expense;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
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

it('lists reservations in-house on the selected day with totals, payments and balance', function (): void {
    $roomType = RoomType::factory()->for($this->hotel)->create(['default_rate' => 80_000_00]);
    $room = Room::factory()->for($this->hotel)->for($roomType)->create(['number' => '101']);
    $guest = Guest::factory()->for($this->hotel)->create(['first_name' => 'Asha', 'last_name' => 'Juma']);

    $reservation = app(CreateReservation::class)->execute(
        guestId: $guest->id,
        roomTypeId: $roomType->id,
        roomId: $room->id,
        arrival: CarbonImmutable::parse('2026-09-14'),
        departure: CarbonImmutable::parse('2026-09-16'),
    );
    $stay = app(CheckInReservation::class)->execute($reservation, $this->user);
    app(RecordPayment::class)->execute($stay->folio, 'cash', Money::fromMajor(50_000), receivedBy: $this->user);

    app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $roomType->id,
        roomId: Room::factory()->for($this->hotel)->for($roomType)->create()->id,
        arrival: CarbonImmutable::parse('2026-09-20'),
        departure: CarbonImmutable::parse('2026-09-21'),
    );

    Livewire::test(CashUp::class, ['date' => '2026-09-15'])
        ->assertSee('In/Out Reservation Reports')
        ->assertSee('Asha Juma')
        ->assertSee('101')
        ->tap(function ($t): void {
            $rows = $t->instance()->reservationRows();
            expect($rows)->toHaveCount(1)
                ->and($rows[0]['nights'])->toBe(2)
                ->and($rows[0]['per_night']->toMajor())->toBe(80_000.0)
                ->and($rows[0]['total']->toMajor())->toBe(160_000.0)
                ->and($rows[0]['paid']->toMajor())->toBe(50_000.0)
                ->and($rows[0]['balance']->toMajor())->toBe(110_000.0)
                ->and($rows[0]['paid_by'])->toBe('Cash')
                ->and($rows[0]['status'])->toBe('Checked In');
        });
});

it('switches to the staff and general payment reports', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'total_amount' => 100_000_00]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-15 10:00:00'));
    app(RecordPayment::class)->execute($folio, 'cash', Money::fromMajor(30_000), receivedBy: $this->user);
    CarbonImmutable::setTestNow();

    Livewire::test(CashUp::class, ['date' => '2026-09-15'])
        ->call('showView', 'staff')
        ->assertSet('view', 'staff')
        ->assertSee('Reservation Staff Payment Report')
        ->assertSee($this->user->name)
        ->assertSee('30,000')
        ->call('showView', 'general')
        ->assertSee('Payment Method')
        ->assertSee('Cash')
        ->call('showView', 'bogus')
        ->assertSet('view', 'reservations');
});

it('validates the report date', function (): void {
    Livewire::test(CashUp::class)
        ->set('date', 'not-a-date')
        ->call('getReport')
        ->assertHasErrors(['date']);
});

it('reports over a from/to date range', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'total_amount' => 100_000_00]);

    foreach (['2026-09-14', '2026-09-15', '2026-09-16', '2026-09-18'] as $day) {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($day.' 10:00:00'));
        app(RecordPayment::class)->execute($folio->fresh(), 'cash', Money::fromMajor(10_000), receivedBy: $this->user);
    }
    CarbonImmutable::setTestNow();

    Livewire::test(CashUp::class, ['date' => '2026-09-15', 'to' => '2026-09-16'])
        ->tap(function ($t): void {
            expect($t->instance()->grandTotal()->toMajor())->toBe(20_000.0);
        });
});

it('defaults the to date to the from date and rejects a to date before it', function (): void {
    Livewire::test(CashUp::class, ['date' => '2026-09-15'])
        ->assertSet('to', '2026-09-15')
        ->set('to', '2026-09-10')
        ->call('getReport')
        ->assertHasErrors(['to']);
});

it('takes expenses paid out off the collected total per method', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'total_amount' => 200_000_00, 'status' => 'open']);
    app(RecordPayment::class)->execute($folio, 'cash', Money::fromMajor(50_000), receivedBy: $this->user);
    Expense::factory()->for($this->hotel)->create([
        'expense_date' => today()->toDateString(),
        'method_code' => 'cash',
        'payee' => 'Market',
        'amount' => 15_000_00,
    ]);

    $cashUp = Livewire::test(CashUp::class, ['view' => 'general'])
        ->assertSee('Paid out')
        ->instance();

    expect($cashUp->methodSummary()['cash']['collected']->amount)->toBe(50_000_00)
        ->and($cashUp->methodSummary()['cash']['paid_out']->amount)->toBe(15_000_00)
        ->and($cashUp->methodSummary()['cash']['net']->amount)->toBe(35_000_00)
        ->and($cashUp->totalPaidOut()->amount)->toBe(15_000_00);
});

it('leaves cancelled bookings out and credits the room only with its share of payments', function (): void {
    $roomType = RoomType::factory()->for($this->hotel)->create(['default_rate' => 100_000_00]);
    $book = fn (): Reservation => app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $roomType->id,
        roomId: Room::factory()->for($this->hotel)->for($roomType)->create()->id,
        arrival: CarbonImmutable::parse('2026-09-14'),
        departure: CarbonImmutable::parse('2026-09-15'),
    );

    app(CancelReservation::class)->execute($book());

    $stay = app(CheckInReservation::class)->execute($book(), $this->user);
    app(PostFolioCharge::class)->price($stay->folio, 'pos_order', null, 'Bar', Money::fromMajor(100_000));
    app(RecordPayment::class)->execute($stay->folio->fresh(), 'cash', Money::fromMajor(100_000), receivedBy: $this->user);

    $rows = Livewire::test(CashUp::class, ['date' => '2026-09-14'])->instance()->reservationRows();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['paid']->toMajor())->toBe(50_000.0)
        ->and($rows[0]['balance']->toMajor())->toBe(50_000.0);
});
