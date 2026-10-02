<?php

declare(strict_types=1);

use App\Domain\Folios\Actions\PostFolioCharge;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Livewire\Admin\Hotels\Show as HotelShow;
use App\Livewire\Extras\Create as ExtrasCreate;
use App\Livewire\POS\Terminal;
use App\Livewire\Reports\ProfitLoss;
use App\Models\ExtraOrder;
use App\Models\ExtraService;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\PosOrder;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Support\Money;
use App\Support\Vat;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

function hotelWithVat(string $mode, float $rate = 18): Hotel
{
    [$hotel] = actingAsTenantUser(Hotel::factory()->create(['vat_mode' => $mode, 'vat_rate' => $rate]));

    return $hotel;
}

function checkInGuest(Hotel $hotel, int $nightlyRateMajor, int $nights): Folio
{
    $roomType = RoomType::factory()->for($hotel)->create(['default_rate' => $nightlyRateMajor * 100]);
    $room = Room::factory()->for($hotel)->for($roomType)->create();

    $reservation = app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($hotel)->create()->id,
        roomTypeId: $roomType->id,
        roomId: $room->id,
        arrival: CarbonImmutable::parse('2026-11-10'),
        departure: CarbonImmutable::parse('2026-11-10')->addDays($nights),
    );

    test()->travelTo(CarbonImmutable::parse('2026-11-10 09:00'));

    return app(CheckInReservation::class)->execute($reservation)->folio;
}

it('splits prices into net, VAT and gross per mode', function (string $mode, int $net, int $tax, int $gross): void {
    $split = (new Vat($mode, 18))->split(Money::fromMajor(118_000));

    expect($split['net']->toMajor())->toBe((float) $net)
        ->and($split['tax']->toMajor())->toBe((float) $tax)
        ->and($split['gross']->toMajor())->toBe((float) $gross);
})->with([
    'no VAT' => [Vat::NONE, 118_000, 0, 118_000],
    'VAT included' => [Vat::INCLUSIVE, 100_000, 18_000, 118_000],
    'VAT added on top' => [Vat::EXCLUSIVE, 118_000, 21_240, 139_240],
]);

it('labels the VAT line with its rate', function (): void {
    expect((new Vat(Vat::INCLUSIVE, 18))->label())->toBe('VAT 18% (included)')
        ->and((new Vat(Vat::EXCLUSIVE, 16.5))->label())->toBe('VAT 16.5%')
        ->and((new Vat)->label())->toBe('VAT');
});

it('splits VAT out of room charges when prices include VAT', function (): void {
    $folio = checkInGuest(hotelWithVat(Vat::INCLUSIVE), 118_000, 2);
    $charge = $folio->charges()->sole();

    expect($charge->total->toMajor())->toBe(236_000.0)
        ->and($charge->tax_amount->toMajor())->toBe(36_000.0)
        ->and($folio->subtotal->toMajor())->toBe(200_000.0)
        ->and($folio->tax_total->toMajor())->toBe(36_000.0)
        ->and($folio->total_amount->toMajor())->toBe(236_000.0);
});

it('adds VAT on top of room charges when prices exclude VAT', function (): void {
    $folio = checkInGuest(hotelWithVat(Vat::EXCLUSIVE), 100_000, 2);

    expect($folio->subtotal->toMajor())->toBe(200_000.0)
        ->and($folio->tax_total->toMajor())->toBe(36_000.0)
        ->and($folio->total_amount->toMajor())->toBe(236_000.0);
});

it('charges no VAT for a hotel without VAT', function (): void {
    $folio = checkInGuest(hotelWithVat(Vat::NONE), 100_000, 2);

    expect($folio->tax_total->isZero())->toBeTrue()
        ->and($folio->total_amount->toMajor())->toBe(200_000.0);
});

it('posts VAT on credits as a negative amount', function (): void {
    $folio = checkInGuest(hotelWithVat(Vat::INCLUSIVE), 118_000, 1);

    $credit = app(PostFolioCharge::class)->price($folio, 'room', null, 'Guest left early', Money::fromMajor(-59_000));
    $folio->refresh();

    expect($credit->tax_amount->toMajor())->toBe(-9_000.0)
        ->and($folio->subtotal->toMajor())->toBe(50_000.0)
        ->and($folio->tax_total->toMajor())->toBe(9_000.0)
        ->and($folio->total_amount->toMajor())->toBe(59_000.0);
});

it('applies VAT to POS orders and carries it onto the folio', function (): void {
    $hotel = hotelWithVat(Vat::EXCLUSIVE);
    $outlet = Outlet::factory()->for($hotel)->create();
    $beer = MenuItem::factory()->for($hotel)->for($outlet)
        ->for(MenuCategory::factory()->for($hotel)->for($outlet), 'category')
        ->create(['price' => 5_000_00]);

    $component = Livewire::test(Terminal::class, ['outlet' => $outlet])
        ->call('addItem', $beer->id)
        ->call('addItem', $beer->id);

    $order = $component->instance()->order();

    expect($order->subtotal->toMajor())->toBe(10_000.0)
        ->and($order->tax_total->toMajor())->toBe(1_800.0)
        ->and($order->total_amount->toMajor())->toBe(11_800.0)
        ->and($order->items->sole()->tax_amount->toMajor())->toBe(1_800.0);

    $component->call('openSettle')->set('settleMethod', 'cash')->call('settleDirect');

    $folio = PosOrder::query()->where('status', 'billed')->sole()->folio;

    expect($folio->tax_total->toMajor())->toBe(1_800.0)
        ->and($folio->total_amount->toMajor())->toBe(11_800.0)
        ->and($folio->status)->toBe('settled')
        ->and($folio->paid_total->toMajor())->toBe(11_800.0);
});

it('charges walk-in extras with VAT added on top', function (): void {
    $hotel = hotelWithVat(Vat::EXCLUSIVE);
    $service = ExtraService::factory()->for($hotel)->create(['price' => 10_000_00]);

    Livewire::test(ExtrasCreate::class)
        ->set('customer', ExtrasCreate::WALK_IN)
        ->set('paymentMethod', 'cash')
        ->set('lines.0.extra_service_id', (string) $service->id)
        ->call('save')
        ->assertHasNoErrors();

    $order = ExtraOrder::query()->sole();

    expect($order->total()->toMajor())->toBe(11_800.0)
        ->and($order->tax()->toMajor())->toBe(1_800.0)
        ->and(Payment::query()->sole()->amount->toMajor())->toBe(11_800.0)
        ->and($order->folio->status)->toBe('settled');
});

it('reports income net of VAT in the profit and loss report', function (): void {
    $hotel = hotelWithVat(Vat::INCLUSIVE);
    CarbonImmutable::setTestNow('2026-11-10 12:00');
    checkInGuest($hotel, 118_000, 1);
    ExtraOrder::factory()->for($hotel)->create(['order_date' => '2026-11-10', 'total_amount' => 11_800_00, 'tax_amount' => 1_800_00]);
    CarbonImmutable::setTestNow();

    $income = Livewire::test(ProfitLoss::class, ['from' => '2026-11-01', 'to' => '2026-11-30'])->instance()->income();

    expect($income['Room Revenue']->toMajor())->toBe(100_000.0)
        ->and($income['Extras Sales']->toMajor())->toBe(10_000.0);
});

it('lets a super-admin set the hotel VAT mode and rate', function (): void {
    $hotel = Hotel::factory()->create();
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(HotelShow::class, ['hotel' => $hotel])
        ->assertSet('vat_mode', Vat::NONE)
        ->set('vat_mode', Vat::INCLUSIVE)
        ->set('vat_rate', '0')
        ->call('saveDetails')
        ->assertHasErrors('vat_rate')
        ->set('vat_rate', '18')
        ->call('saveDetails')
        ->assertHasNoErrors();

    expect($hotel->fresh())
        ->vat_mode->toBe(Vat::INCLUSIVE)
        ->and($hotel->fresh()->vat()->label())->toBe('VAT 18% (included)');
});

it('shows the VAT line on the printed folio', function (): void {
    $folio = checkInGuest(hotelWithVat(Vat::INCLUSIVE), 118_000, 1);

    $this->get(route('folios.print', $folio))
        ->assertOk()
        ->assertSee('Subtotal (before VAT)')
        ->assertSee('VAT 18% (included)');
});
