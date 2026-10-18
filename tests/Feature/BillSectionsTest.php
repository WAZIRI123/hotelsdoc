<?php

declare(strict_types=1);

use App\Domain\Folios\BillSection;
use App\Domain\Folios\FolioSections;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Livewire\Extras\Create as ExtrasCreate;
use App\Livewire\Folios\RecordPaymentForm;
use App\Livewire\Folios\Show as FolioShow;
use App\Livewire\POS\Terminal;
use App\Models\ExtraService;
use App\Models\Guest;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Stay;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    // Bookings cannot arrive in the past, so the clock starts on the arrival day used here.
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00'));

    $roomType = RoomType::factory()->for($this->hotel)->create(['default_rate' => 100_000_00]);
    $room = Room::factory()->for($this->hotel)->for($roomType)->create();
    $guest = Guest::factory()->for($this->hotel)->create(['first_name' => 'Asha']);

    $reservation = app(CreateReservation::class)->execute(
        guestId: $guest->id,
        roomTypeId: $roomType->id,
        roomId: $room->id,
        arrival: CarbonImmutable::parse('2026-10-01'),
        departure: CarbonImmutable::parse('2026-10-03'),
    );
    $this->stay = app(CheckInReservation::class)->execute($reservation, $this->user);

    $this->outlet = Outlet::factory()->for($this->hotel)->create(['name' => 'Main Bar']);
    $category = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();
    $this->beer = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($category, 'category')->create([
        'name' => 'Tusker',
        'price' => 5_000_00,
    ]);
    $this->laundry = ExtraService::factory()->for($this->hotel)->create(['name' => 'Laundry', 'price' => 10_000_00]);
});

function sectionsOf(Stay $stay): FolioSections
{
    return FolioSections::for($stay->folio->fresh());
}

function billBeerToRoom(Stay $stay, MenuItem $beer, Outlet $outlet): void
{
    Livewire::test(Terminal::class, ['outlet' => $outlet])
        ->call('addItem', $beer->id)
        ->call('openSettle')
        ->set('billToStayId', $stay->id)
        ->call('billToRoom');
}

it('splits a guest billing into accommodation, outlets and extras', function (): void {
    billBeerToRoom($this->stay, $this->beer, $this->outlet);

    $sections = sectionsOf($this->stay);
    $folio = $this->stay->folio->fresh();

    expect($sections->get(BillSection::Outlets)['total']->amount)->toBe(PosOrder::query()->sole()->total_amount->amount)
        ->and($sections->get(BillSection::Extras)['total']->amount)->toBe(0)
        ->and($sections->get(BillSection::Accommodation)['total']->amount + $sections->get(BillSection::Outlets)['total']->amount)
        ->toBe($folio->total_amount->amount)
        ->and(array_keys($sections->used()))->toBe(['accommodation', 'outlets']);
});

it('lets an in-house guest pay an outlet order at the counter onto their billing', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('openSettle')
        ->set('billToStayId', $this->stay->id)
        ->set('roomSettle', 'pay_now')
        ->set('settleMethod', 'cash')
        ->call('payForGuest')
        ->assertDispatched('open-print');

    $order = PosOrder::query()->sole();
    $folio = $this->stay->folio->fresh();
    $sections = sectionsOf($this->stay);

    expect($order->status)->toBe('billed')
        ->and($order->folio_id)->toBe($folio->id)
        ->and($folio->payments()->sole()->bill_section)->toBe('outlets')
        ->and($sections->balance(BillSection::Outlets)->isZero())->toBeTrue()
        ->and($sections->balance(BillSection::Accommodation)->isPositive())->toBeTrue()
        ->and($folio->status)->toBe('open');
});

it('lets an in-house guest pay part of an outlet order now and the rest later on their bill', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('addItem', $this->beer->id)
        ->call('openSettle')
        ->set('billToStayId', $this->stay->id)
        ->set('roomSettle', 'pay_now')
        ->set('settleMethod', 'cash')
        ->set('tenderAmount', '4000')
        ->call('payForGuest')
        ->assertHasNoErrors()
        ->assertDispatched('open-print');

    $order = PosOrder::query()->sole();
    $folio = $this->stay->folio->fresh();
    $payment = $folio->payments()->sole();

    expect($order->status)->toBe('billed')
        ->and($order->folio_id)->toBe($folio->id)
        ->and($payment->amount->amount)->toBe(4_000_00)
        ->and($payment->bill_section)->toBe('outlets')
        ->and($payment->pos_order_id)->toBe($order->id)
        ->and(sectionsOf($this->stay)->balance(BillSection::Outlets)->amount)->toBe($order->total_amount->amount - 4_000_00)
        ->and($order->unpaidAmount()->amount)->toBe($order->total_amount->amount - 4_000_00)
        ->and($folio->status)->toBe('open');

    $this->get(route('pos.transactions.print', $order))
        ->assertOk()
        ->assertSeeInOrder([__('Left on guest billing'), $order->unpaidAmount()->formatExact(), $folio->reference]);
});

it('shows only the order\'s own payments on an in-house guest\'s receipt', function (): void {
    app(RecordPayment::class)->execute($this->stay->folio, 'cash', Money::fromMajor(77_777), section: BillSection::Accommodation);

    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('openSettle')
        ->set('billToStayId', $this->stay->id)
        ->set('roomSettle', 'pay_now')
        ->set('settleMethod', 'cash')
        ->call('payForGuest');

    $this->get(route('pos.transactions.print', PosOrder::query()->sole()))
        ->assertOk()
        ->assertDontSee('77,777')
        ->assertDontSee(__('Left on guest billing'));
});

it('takes only the order total from an in-house guest who hands over more', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('openSettle')
        ->set('billToStayId', $this->stay->id)
        ->set('roomSettle', 'pay_now')
        ->set('settleMethod', 'cash')
        ->set('tenderAmount', '50000')
        ->call('payForGuest')
        ->assertHasNoErrors();

    expect($this->stay->folio->fresh()->payments()->sole()->amount->amount)->toBe(PosOrder::query()->sole()->total_amount->amount)
        ->and(sectionsOf($this->stay)->balance(BillSection::Outlets)->isZero())->toBeTrue();
});

it('charges an outlet order to the billing when the guest does not pay now', function (): void {
    billBeerToRoom($this->stay, $this->beer, $this->outlet);

    expect($this->stay->folio->fresh()->payments()->count())->toBe(0)
        ->and(sectionsOf($this->stay)->balance(BillSection::Outlets)->isPositive())->toBeTrue();
});

it('lets an in-house guest pay extras now against the extras section', function (): void {
    Livewire::test(ExtrasCreate::class)
        ->set('customer', (string) $this->stay->id)
        ->set('guestSettle', 'pay_now')
        ->set('paymentMethod', 'cash')
        ->set('lines.0.extra_service_id', (string) $this->laundry->id)
        ->call('save')
        ->assertHasNoErrors();

    $payment = $this->stay->folio->fresh()->payments()->sole();

    expect($payment->bill_section)->toBe('extras')
        ->and(sectionsOf($this->stay)->balance(BillSection::Extras)->isZero())->toBeTrue();
});

it('requires a payment method when an in-house guest pays extras now', function (): void {
    Livewire::test(ExtrasCreate::class)
        ->set('customer', (string) $this->stay->id)
        ->set('guestSettle', 'pay_now')
        ->set('lines.0.extra_service_id', (string) $this->laundry->id)
        ->call('save')
        ->assertHasErrors('paymentMethod');
});

it('still charges in-house extras to the billing without a payment by default', function (): void {
    Livewire::test(ExtrasCreate::class)
        ->set('customer', (string) $this->stay->id)
        ->set('lines.0.extra_service_id', (string) $this->laundry->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->stay->folio->fresh()->payments()->count())->toBe(0);
});

it('pays one section from the guest billing with its balance filled in', function (): void {
    billBeerToRoom($this->stay, $this->beer, $this->outlet);
    $outletBalance = sectionsOf($this->stay)->balance(BillSection::Outlets);

    Livewire::test(RecordPaymentForm::class, ['folio' => $this->stay->folio->fresh()])
        ->call('openFor', 'outlets')
        ->assertSet('section', 'outlets')
        ->assertSet('tenderAmount', (int) $outletBalance->toMajor())
        ->set('method_code', 'cash')
        ->call('submit')
        ->assertDispatched('payment-recorded');

    $sections = sectionsOf($this->stay);

    expect($sections->balance(BillSection::Outlets)->isZero())->toBeTrue()
        ->and($sections->balance(BillSection::Accommodation)->isPositive())->toBeTrue();
});

it('pays every section at once with a whole-bill payment', function (): void {
    billBeerToRoom($this->stay, $this->beer, $this->outlet);
    $folio = $this->stay->folio->fresh();

    Livewire::test(RecordPaymentForm::class, ['folio' => $folio])
        ->call('openFor')
        ->assertSet('section', '')
        ->assertSet('tenderAmount', (int) $folio->total_amount->toMajor())
        ->set('method_code', 'cash')
        ->call('submit');

    $folio = $folio->fresh();
    $sections = sectionsOf($this->stay);

    expect($folio->status)->toBe('settled')
        ->and($folio->payments()->sole()->bill_section)->toBeNull()
        ->and($sections->balance(BillSection::Accommodation)->isZero())->toBeTrue()
        ->and($sections->balance(BillSection::Outlets)->isZero())->toBeTrue();
});

it('fills sections still owing from a whole-bill payment after a section was paid', function (): void {
    billBeerToRoom($this->stay, $this->beer, $this->outlet);
    $folio = $this->stay->folio->fresh();
    $outlets = sectionsOf($this->stay)->balance(BillSection::Outlets);

    app(RecordPayment::class)->execute($folio, 'cash', $outlets, section: BillSection::Outlets);
    $folio = $folio->fresh();
    app(RecordPayment::class)->execute($folio, 'cash', $folio->total_amount->subtract($folio->paid_total));

    $sections = sectionsOf($this->stay);

    expect($folio->fresh()->status)->toBe('settled')
        ->and($sections->balance(BillSection::Accommodation)->isZero())->toBeTrue()
        ->and($sections->credit->isZero())->toBeTrue();
});

it('moves a section overpayment on to the sections still owing', function (): void {
    billBeerToRoom($this->stay, $this->beer, $this->outlet);
    $folio = $this->stay->folio->fresh();
    $outlets = sectionsOf($this->stay)->get(BillSection::Outlets)['total'];

    app(RecordPayment::class)->execute($folio, 'cash', $outlets->add(Money::fromMajor(1_000)), section: BillSection::Outlets);

    $sections = sectionsOf($this->stay);

    expect($sections->get(BillSection::Outlets)['paid']->amount)->toBe($outlets->amount)
        ->and($sections->get(BillSection::Accommodation)['paid']->amount)->toBe(Money::fromMajor(1_000)->amount);
});

it('shows each section with its own pay button, and pay all, on the guest billing page', function (): void {
    billBeerToRoom($this->stay, $this->beer, $this->outlet);

    Livewire::test(FolioShow::class, ['folio' => $this->stay->folio->fresh()])
        ->assertSee('Accommodation')
        ->assertSee('Pay Outlets')
        ->assertSee('Pay all')
        ->call('openPayment', 'outlets')
        ->assertDispatched('open-payment', section: 'outlets');
});
