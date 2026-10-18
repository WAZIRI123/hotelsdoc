<?php

declare(strict_types=1);

use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Reservations\Actions\CancelReservation;
use App\Domain\Reservations\Actions\CheckInReservation;
use App\Domain\Reservations\Actions\CreateReservation;
use App\Livewire\Reports\Agents;
use App\Livewire\Reports\CashUp;
use App\Livewire\Reports\Credit;
use App\Livewire\Reports\DrinksSales;
use App\Livewire\Reports\Expenses;
use App\Livewire\Reports\ExtrasSales;
use App\Livewire\Reports\FoodSales;
use App\Livewire\Reports\ProfitLoss;
use App\Livewire\Reports\StockProducts;
use App\Models\Agent;
use App\Models\Expense;
use App\Models\ExtraOrder;
use App\Models\ExtraService;
use App\Models\Folio;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\PosOrder;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\StockEntry;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates a billed POS order with one line for the given menu item.
 */
function billedPosOrder(MenuItem $item, int $quantity, string $billedAt, int $taxPerUnit = 0): PosOrder
{
    $subtotal = $item->price->amount * $quantity;
    $tax = $taxPerUnit * $quantity;

    $order = PosOrder::factory()->for($item->outlet->hotel)->for($item->outlet)->create([
        'status' => 'billed',
        'subtotal' => $subtotal,
        'tax_total' => $tax,
        'total_amount' => $subtotal + $tax,
        'billed_at' => CarbonImmutable::parse($billedAt),
    ]);

    $order->items()->create([
        'hotel_id' => $order->hotel_id,
        'menu_item_id' => $item->id,
        'name_snapshot' => $item->name,
        'quantity' => $quantity,
        'unit_price' => $item->price->amount,
        'tax_amount' => $tax,
        'line_total' => $subtotal + $tax,
    ]);

    return $order;
}

function menuItemAt(Hotel $hotel, string $outletType, string $name, int $priceMajor, array $attributes = []): MenuItem
{
    $outlet = Outlet::factory()->for($hotel)->create(['type' => $outletType, 'name' => ucfirst($outletType).' Outlet']);
    $category = MenuCategory::factory()->for($hotel)->for($outlet)->create();

    return MenuItem::factory()->for($hotel)->for($outlet)->for($category, 'category')->create([
        'name' => $name,
        'price' => Money::fromMajor($priceMajor),
        ...$attributes,
    ]);
}

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser(role: 'accountant');
    // Bookings cannot arrive in the past, so the clock starts on the first arrival day used here.
    $this->travelTo(CarbonImmutable::parse('2026-08-01 09:00'));
});

it('splits POS sales into drinks and food by outlet type', function (): void {
    $beer = menuItemAt($this->hotel, 'bar', 'Tusker', 5_000);
    $pilau = menuItemAt($this->hotel, 'restaurant', 'Pilau', 12_000);

    billedPosOrder($beer, 3, '2026-09-10 20:00');
    billedPosOrder($beer, 1, '2026-09-11 20:00');
    billedPosOrder($pilau, 2, '2026-09-10 13:00');
    billedPosOrder($beer, 9, '2026-08-01 20:00');

    $drinks = Livewire::test(DrinksSales::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->assertSet('view', 'orders')
        ->assertSeeInOrder(['Date', 'Customer', 'Currency', 'Total', 'Discount', 'Grand Total', 'Paid By', 'Amount Paid', 'Balance'])
        ->call('showView', 'items')
        ->assertSee('Tusker')
        ->assertDontSee('Pilau');

    expect($drinks->instance()->totals())
        ->orders->toBe(2)
        ->quantity->toBe(4)
        ->and($drinks->instance()->totals()['total']->toMajor())->toBe(20_000.0);

    $food = Livewire::test(FoodSales::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->assertSet('view', 'orders')
        ->call('showView', 'items')
        ->assertSee('Pilau');

    expect($food->instance()->totals()['total']->toMajor())->toBe(24_000.0);
});

it('lists food orders with customer, payments and balance', function (): void {
    $pilau = menuItemAt($this->hotel, 'restaurant', 'Pilau', 12_000);
    $paidOrder = billedPosOrder($pilau, 1, '2026-09-10 13:00');
    $paidFolio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'status' => 'open', 'total_amount' => 12_000_00]);
    $paidOrder->update(['folio_id' => $paidFolio->id]);
    app(RecordPayment::class)->execute($paidFolio, 'mpesa', Money::fromMajor(12_000), reference: 'M1', receivedBy: $this->user);

    $guest = Guest::factory()->for($this->hotel)->create(['first_name' => 'Asha', 'last_name' => 'Juma']);
    $unpaidOrder = billedPosOrder($pilau, 2, '2026-09-11 13:00');
    $unpaidOrder->update(['folio_id' => Folio::query()->create(['hotel_id' => $this->hotel->id, 'guest_id' => $guest->id, 'status' => 'open', 'total_amount' => 24_000_00])->id]);

    $component = Livewire::test(FoodSales::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->assertSet('view', 'orders')
        ->assertSeeInOrder(['Date', 'Customer', 'Currency', 'Total', 'Discount', 'Grand Total', 'Paid By', 'Amount Paid', 'Balance'])
        ->assertSee('Asha Juma')
        ->assertSee('Mpesa');

    [$paid, $unpaid] = $component->instance()->orderRows();

    expect($paid['customer'])->toBeNull()
        ->and($paid['paid']->toMajor())->toBe(12_000.0)
        ->and($paid['balance']->toMajor())->toBe(0.0)
        ->and($unpaid['paid']->toMajor())->toBe(0.0)
        ->and($unpaid['balance']->toMajor())->toBe(24_000.0)
        ->and($component->instance()->totals()['balance']->toMajor())->toBe(24_000.0);
});

it('summarises extras sales per service in the hotel currency', function (): void {
    $order = ExtraOrder::factory()->for($this->hotel)->create(['order_date' => '2026-09-12', 'total_amount' => 30_000_00]);
    $service = ExtraService::factory()->for($this->hotel)->create(['name' => 'Laundry']);
    $order->items()->create(['hotel_id' => $this->hotel->id, 'extra_service_id' => $service->id, 'name_snapshot' => 'Laundry', 'quantity' => 3, 'unit_price' => 10_000_00, 'line_total' => 30_000_00]);

    $component = Livewire::test(ExtrasSales::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->assertSee('Currency: TZS')
        ->assertSet('view', 'orders')
        ->assertSeeInOrder(['Date', 'Customer', 'Currency', 'Total', 'Discount', 'Grand Total', 'Paid By', 'Amount Paid', 'Balance'])
        ->assertSee('Counter')
        ->call('showView', 'items')
        ->assertSee('Laundry');

    expect($component->instance()->totals())
        ->orders->toBe(1)
        ->walk_in->toBe(1)
        ->quantity->toBe(3)
        ->and($component->instance()->totals()['total']->toMajor())->toBe(30_000.0);
});

it('credits extras posted to a guest folio with the paid share of that folio', function (): void {
    $guest = Guest::factory()->for($this->hotel)->create(['first_name' => 'Asha', 'last_name' => 'Juma']);
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'guest_id' => $guest->id, 'status' => 'open', 'total_amount' => 100_000_00]);
    app(RecordPayment::class)->execute($folio, 'cash', Money::fromMajor(25_000), receivedBy: $this->user);
    ExtraOrder::factory()->for($this->hotel)->create(['order_date' => '2026-09-12', 'folio_id' => $folio->id, 'total_amount' => 40_000_00]);

    $component = Livewire::test(ExtrasSales::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->assertSee('Cash');

    $row = $component->instance()->orderRows()[0];

    expect($row['paid']->toMajor())->toBe(10_000.0)
        ->and($row['balance']->toMajor())->toBe(30_000.0)
        ->and($component->instance()->totals()['balance']->toMajor())->toBe(30_000.0);
});

it('reports stock on hand, units sold and low stock', function (): void {
    $beer = menuItemAt($this->hotel, 'bar', 'Tusker', 5_000, ['track_stock' => true, 'stock_quantity' => 3]);
    menuItemAt($this->hotel, 'bar', 'Untracked Soda', 2_000);
    billedPosOrder($beer, 4, '2026-09-10 20:00');

    $component = Livewire::test(StockProducts::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->call('showView', 'on-hand')
        ->assertSee('Tusker')
        ->assertSee('Low Stock');

    $row = $component->instance()->rows()[0];

    expect(array_column($component->instance()->rows(), 'name'))->toBe(['Tusker']);

    expect($row['sold'])->toBe(4)
        ->and($row['in_stock'])->toBe(3)
        ->and($row['value']->toMajor())->toBe(15_000.0)
        ->and($component->instance()->totals()['low'])->toBe(1);
});

it('lists stock received in the period with expiry dates and a total', function (): void {
    CarbonImmutable::setTestNow('2026-09-20 09:00:00');
    $beer = menuItemAt($this->hotel, 'bar', 'Tusker', 5_000, ['track_stock' => true]);

    StockEntry::factory()->for($this->hotel)->for($beer)->create(['entry_date' => '2026-09-05', 'expiry_date' => '2026-09-10', 'description' => 'Crate from SBL', 'quantity' => 24]);
    StockEntry::factory()->for($this->hotel)->for($beer)->create(['entry_date' => '2026-09-15', 'expiry_date' => '2027-03-01', 'description' => 'Second crate', 'quantity' => 12]);
    StockEntry::factory()->for($this->hotel)->for($beer)->create(['entry_date' => '2026-08-15', 'description' => 'August delivery', 'quantity' => 6]);

    $component = Livewire::test(StockProducts::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->assertSet('view', 'received')
        ->assertSeeInOrder(['Date', 'Product', 'Expiring Date', 'Description', 'Quantity'])
        ->assertSeeInOrder(['05-09-2026', 'Tusker', '10-09-2026', 'expired', 'Crate from SBL', '24.00'])
        ->assertDontSee('August delivery')
        ->assertSee('36.00');

    [$expired, $fresh] = $component->instance()->entries()->all();

    expect($component->instance()->expiryState($expired))->toBe('expired')
        ->and($component->instance()->expiryState($fresh))->toBeNull();

    CarbonImmutable::setTestNow();
});

it('lets an accountant record stock received and adds it to the stock on hand', function (): void {
    $soda = menuItemAt($this->hotel, 'bar', 'Coke', 1_500);

    Livewire::test(StockProducts::class)
        ->call('openCreate')
        ->set('entryItem', (string) $soda->id)
        ->set('entryExpiry', '2027-01-31')
        ->set('entryDescription', 'Invoice 1042')
        ->set('entryQuantity', '48')
        ->set('entryUnitCost', '900')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Invoice 1042');

    $entry = StockEntry::query()->sole();

    expect($entry->quantity)->toBe(48)
        ->and($entry->unit_cost)->toBe(900_00)
        ->and($entry->expiry_date?->toDateString())->toBe('2027-01-31')
        ->and($entry->recorded_by)->toBe($this->user->id)
        ->and($soda->fresh()->track_stock)->toBeTrue()
        ->and($soda->fresh()->stock_quantity)->toBe(48);

    Livewire::test(StockProducts::class)->call('delete', $entry->id);

    expect(StockEntry::query()->count())->toBe(0)
        ->and($soda->fresh()->stock_quantity)->toBe(0);
});

it('validates stock entries', function (): void {
    Livewire::test(StockProducts::class)
        ->call('openCreate')
        ->set('entryExpiry', '2000-01-01')
        ->set('entryQuantity', '0')
        ->call('save')
        ->assertHasErrors(['entryItem', 'entryExpiry', 'entryQuantity', 'entryUnitCost' => 'required']);
});

it('hides stock recording from roles without manage-stock', function (): void {
    actingAsTenantUser($this->hotel, User::factory()->create(), 'cashier');
    Role::query()->where('name', 'cashier')->where('hotel_id', $this->hotel->id)->firstOrFail()->givePermissionTo('view-reports');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Livewire::test(StockProducts::class)
        ->assertDontSee('Record Stock')
        ->call('openCreate')
        ->assertForbidden();
});

it('lets an accountant record and void expenses', function (): void {
    PaymentMethod::query()->firstOrCreate(['code' => 'cash'], ['label' => 'Cash', 'is_active' => true, 'sort_order' => 0]);

    Livewire::test(Expenses::class)
        ->call('openCreate')
        ->set('expensePvNumber', 'PV-0042')
        ->set('expenseCategory', 'Utilities')
        ->set('expenseDescription', 'Electricity bill')
        ->set('expenseQuantity', '2')
        ->set('expensePayee', 'TANESCO')
        ->set('expenseMethod', 'cash')
        ->set('expenseAmount', '150000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee(['PV-0042', 'Electricity bill', '150,000.00']);

    $expense = Expense::query()->sole();

    expect($expense->money()->toMajor())->toBe(150_000.0)
        ->and($expense->pv_number)->toBe('PV-0042')
        ->and($expense->quantity)->toBe('2.00')
        ->and($expense->recorded_by)->toBe($this->user->id)
        ->and($expense->currency)->toBe('TZS');

    Livewire::test(Expenses::class)
        ->call('startVoidExpense', $expense->id)
        ->set('voidReason', 'Entered twice')
        ->call('voidExpense')
        ->assertHasNoErrors();

    expect($expense->fresh()->isVoided())->toBeTrue()
        ->and(Expense::query()->count())->toBe(1);
});

it('validates expenses', function (): void {
    Livewire::test(Expenses::class)
        ->call('openCreate')
        ->set('expenseCategory', 'Not a category')
        ->set('expenseQuantity', '-1')
        ->set('expenseAmount', '0')
        ->call('save')
        ->assertHasErrors(['expenseCategory', 'expenseDescription', 'expenseQuantity', 'expenseAmount']);
});

it('lists expenses in the period with category totals', function (): void {
    Expense::factory()->for($this->hotel)->create(['expense_date' => '2026-09-05', 'category' => 'Utilities', 'description' => 'Water', 'amount' => 40_000_00]);
    Expense::factory()->for($this->hotel)->create(['expense_date' => '2026-09-06', 'category' => 'Utilities', 'description' => 'Power', 'amount' => 60_000_00]);
    Expense::factory()->for($this->hotel)->create(['expense_date' => '2026-08-06', 'category' => 'Rent', 'description' => 'August rent']);
    Expense::factory()->for($this->hotel)->voided('Duplicate entry')->create(['expense_date' => '2026-09-07', 'category' => 'Utilities', 'description' => 'Power again', 'amount' => 60_000_00]);

    $component = Livewire::test(Expenses::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->assertSeeInOrder(['Date', 'PV Number', 'Item', 'Category', 'Quantity', 'Amount'])
        ->assertSee('Water')
        ->assertSeeInOrder(['Total', '100,000.00'])
        ->assertSee(['Power again', 'Duplicate entry'])
        ->assertDontSee('August rent');

    expect($component->instance()->grandTotal()->toMajor())->toBe(100_000.0)
        ->and($component->instance()->totalsByCategory()['Utilities']['count'])->toBe(2);
});

it('shows a zero total when there are no expenses', function (): void {
    Livewire::test(Expenses::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->assertSee('No expenses in this period.')
        ->assertSeeInOrder(['Total', '0.00']);
});

it('hides expense recording from users without manage-payments', function (): void {
    actingAsTenantUser($this->hotel, User::factory()->create(), 'manager');
    $role = Role::query()->where('name', 'manager')->where('hotel_id', $this->hotel->id)->firstOrFail();
    $role->revokePermissionTo('manage-payments');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Livewire::test(Expenses::class)
        ->assertDontSee('Record Expense')
        ->call('openCreate')
        ->assertForbidden();
});

it('lists folios with an outstanding balance in the credit report', function (): void {
    $guest = Guest::factory()->for($this->hotel)->create(['first_name' => 'Owing', 'last_name' => 'Guest']);
    $owing = Folio::query()->create(['hotel_id' => $this->hotel->id, 'guest_id' => $guest->id, 'status' => 'open', 'total_amount' => 100_000_00]);
    app(RecordPayment::class)->execute($owing, 'cash', Money::fromMajor(30_000), receivedBy: $this->user);

    $settledGuest = Guest::factory()->for($this->hotel)->create(['first_name' => 'Settled', 'last_name' => 'Guest']);
    $settled = Folio::query()->create(['hotel_id' => $this->hotel->id, 'guest_id' => $settledGuest->id, 'status' => 'open', 'total_amount' => 20_000_00]);
    app(RecordPayment::class)->execute($settled, 'cash', Money::fromMajor(20_000), receivedBy: $this->user);

    $component = Livewire::test(Credit::class)
        ->assertSee('Owing Guest')
        ->assertDontSee('Settled Guest');

    expect($component->instance()->totals())
        ->count->toBe(1)
        ->and($component->instance()->totals()['total']->toMajor())->toBe(70_000.0);
});

it('splits credit by accommodation, restaurant and bar within the period', function (): void {
    CarbonImmutable::setTestNow('2026-09-14 12:00:00');

    $guest = Guest::factory()->for($this->hotel)->create(['first_name' => 'Owing', 'last_name' => 'Guest']);
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'guest_id' => $guest->id, 'status' => 'open', 'total_amount' => 100_000_00]);
    $folio->charges()->create(['source_type' => 'room', 'description' => 'Room', 'quantity' => 1, 'unit_price' => Money::fromMajor(60_000), 'tax_amount' => Money::zero(), 'total' => Money::fromMajor(60_000), 'posted_at' => now()]);

    foreach (['restaurant' => 30_000, 'bar' => 10_000] as $outletType => $total) {
        $order = billedPosOrder(menuItemAt($this->hotel, $outletType, ucfirst($outletType).' item', $total), 1, '2026-09-14 12:00');
        $folio->charges()->create(['source_type' => 'pos_order', 'source_id' => $order->id, 'description' => 'POS', 'quantity' => 1, 'unit_price' => Money::fromMajor($total), 'tax_amount' => Money::zero(), 'total' => Money::fromMajor($total), 'posted_at' => now()]);
    }

    app(RecordPayment::class)->execute($folio, 'cash', Money::fromMajor(50_000), receivedBy: $this->user);

    CarbonImmutable::setTestNow('2026-08-10 12:00:00');
    $earlierGuest = Guest::factory()->for($this->hotel)->create(['first_name' => 'August', 'last_name' => 'Guest']);
    Folio::query()->create(['hotel_id' => $this->hotel->id, 'guest_id' => $earlierGuest->id, 'status' => 'open', 'total_amount' => 20_000_00]);
    CarbonImmutable::setTestNow('2026-09-14 12:00:00');

    $component = Livewire::test(Credit::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->assertSeeInOrder(['Date', 'Customer', 'Accommodation', 'Restaurant', 'Bar', 'Total'])
        ->assertSee('Owing Guest')
        ->assertDontSee('August Guest')
        ->assertSeeInOrder(['Accommodation Total Credit', '30,000', 'Restaurant Total Credits', '15,000', 'Bar Total Credits', '5,000', 'Total Credits', '50,000.00']);

    $totals = $component->instance()->totals();

    expect($totals['accommodation']->toMajor())->toBe(30_000.0)
        ->and($totals['restaurant']->toMajor())->toBe(15_000.0)
        ->and($totals['bar']->toMajor())->toBe(5_000.0)
        ->and($totals['total']->toMajor())->toBe(50_000.0);
});

it('nets income against expenses in the profit and loss report', function (): void {
    CarbonImmutable::setTestNow('2026-09-14 12:00:00');

    $roomType = RoomType::factory()->for($this->hotel)->create(['default_rate' => 80_000_00]);
    $room = Room::factory()->for($this->hotel)->for($roomType)->create();
    $reservation = app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $roomType->id,
        roomId: $room->id,
        arrival: CarbonImmutable::parse('2026-09-14'),
        departure: CarbonImmutable::parse('2026-09-16'),
    );
    app(CheckInReservation::class)->execute($reservation, $this->user);
    CarbonImmutable::setTestNow();

    billedPosOrder(menuItemAt($this->hotel, 'bar', 'Tusker', 5_000), 2, '2026-09-14 20:00', taxPerUnit: 900_00);
    billedPosOrder(menuItemAt($this->hotel, 'restaurant', 'Pilau', 12_000), 1, '2026-09-14 13:00');
    ExtraOrder::factory()->for($this->hotel)->create(['order_date' => '2026-09-14', 'total_amount' => 15_000_00]);
    Expense::factory()->for($this->hotel)->create(['expense_date' => '2026-09-14', 'category' => 'Utilities', 'amount' => 50_000_00]);
    Expense::factory()->for($this->hotel)->voided()->create(['expense_date' => '2026-09-14', 'category' => 'Rent', 'amount' => 90_000_00]);

    $component = Livewire::test(ProfitLoss::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->assertSeeInOrder(['Food &amp; Drinks sales', 'Accommodation sales', 'Extras sales', 'Total Expenses', 'Expenses', 'Balance'], false)
        ->assertSeeInOrder(['22,000', '160,000', '15,000', '50,000', 'Utilities:', '147,000']);

    $income = $component->instance()->income();

    expect($income['Room Revenue']->toMajor())->toBe(160_000.0)
        ->and($income['Drinks Sales']->toMajor())->toBe(10_000.0)
        ->and($income['Food Sales']->toMajor())->toBe(12_000.0)
        ->and($income['Extras Sales']->toMajor())->toBe(15_000.0)
        ->and($component->instance()->totalIncome()->toMajor())->toBe(197_000.0)
        ->and($component->instance()->totalExpenses()->toMajor())->toBe(50_000.0)
        ->and($component->instance()->netProfit()->toMajor())->toBe(147_000.0)
        ->and($component->instance()->salesColumns()['food_drinks']->toMajor())->toBe(22_000.0)
        ->and($component->instance()->salesColumns()['accommodation']->toMajor())->toBe(160_000.0)
        ->and($component->instance()->salesColumns()['extras']->toMajor())->toBe(15_000.0);
});

it('counts stock received in the period as stock purchases in the profit and loss report', function (): void {
    $beer = menuItemAt($this->hotel, 'bar', 'Safari', 4_000);
    StockEntry::factory()->for($this->hotel)->for($beer)->create(['entry_date' => '2026-09-05', 'quantity' => 24, 'unit_cost' => 2_500_00]);
    StockEntry::factory()->for($this->hotel)->for($beer)->create(['entry_date' => '2026-09-20', 'quantity' => 10, 'unit_cost' => 2_600_00]);
    StockEntry::factory()->for($this->hotel)->for($beer)->create(['entry_date' => '2026-08-30', 'quantity' => 50, 'unit_cost' => 2_500_00]);
    Expense::factory()->for($this->hotel)->create(['expense_date' => '2026-09-14', 'category' => 'Utilities', 'amount' => 50_000_00]);

    $component = Livewire::test(ProfitLoss::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->assertSeeInOrder(['Stock Purchases:', '86,000', 'Utilities:', '50,000']);

    expect($component->instance()->stockPurchases()->toMajor())->toBe(24 * 2_500.0 + 10 * 2_600.0)
        ->and($component->instance()->totalExpenses()->toMajor())->toBe(136_000.0)
        ->and($component->instance()->netProfit()->toMajor())->toBe(-136_000.0);
});

it('counts every row of a hotel that runs in a currency other than TZS', function (): void {
    [$hotel] = actingAsTenantUser(Hotel::factory()->create(['currency' => 'KES']));

    $folio = Folio::factory()->for($hotel)->create();
    $folio->charges()->create(['source_type' => 'room', 'description' => 'Room 1', 'total' => Money::fromMajor(8_000), 'posted_at' => '2026-09-14 12:00']);
    ExtraOrder::factory()->for($hotel)->create(['order_date' => '2026-09-14', 'currency' => 'KES', 'total_amount' => 1_500_00]);
    Expense::factory()->for($hotel)->create(['expense_date' => '2026-09-14', 'category' => 'Utilities', 'currency' => 'KES', 'amount' => 2_000_00]);

    $component = Livewire::test(ProfitLoss::class, ['from' => '2026-09-01', 'to' => '2026-09-30']);

    expect($folio->currency)->toBe('KES')
        ->and($component->instance()->income()['Room Revenue']->toMajor())->toBe(8_000.0)
        ->and($component->instance()->income()['Extras Sales']->toMajor())->toBe(1_500.0)
        ->and($component->instance()->totalExpenses()->toMajor())->toBe(2_000.0)
        ->and($component->instance()->netProfit()->format())->toBe('KES 7,500');
});

it('totals bookings, room nights, revenue and balances per agent', function (): void {
    $roomType = RoomType::factory()->for($this->hotel)->create(['default_rate' => 100_000_00]);
    $rooms = Room::factory()->for($this->hotel)->for($roomType)->count(3)->create();
    $safari = Agent::factory()->for($this->hotel)->create(['name' => 'Safari Tours']);
    $coastal = Agent::factory()->for($this->hotel)->create(['name' => 'Coastal Travel']);
    $book = fn (Room $room, ?Agent $agent, string $arrival, string $departure) => app(CreateReservation::class)->execute(
        guestId: Guest::factory()->for($this->hotel)->create()->id,
        roomTypeId: $roomType->id,
        roomId: $room->id,
        arrival: CarbonImmutable::parse($arrival),
        departure: CarbonImmutable::parse($departure),
        agentId: $agent?->id,
    );

    $checkedIn = $book($rooms[0], $safari, '2026-09-10', '2026-09-13');
    $book($rooms[1], $safari, '2026-09-20', '2026-09-21');
    app(CancelReservation::class)->execute($book($rooms[2], $safari, '2026-09-25', '2026-09-27'));
    $book($rooms[1], $coastal, '2026-09-05', '2026-09-07');
    $book($rooms[2], null, '2026-09-05', '2026-09-06');
    $book($rooms[2], $coastal, '2026-10-05', '2026-10-06');

    $this->travelTo(CarbonImmutable::parse('2026-09-10 14:00'));
    $folio = app(CheckInReservation::class)->execute($checkedIn, $this->user)->folio;
    app(RecordPayment::class)->execute($folio, 'cash', Money::fromMajor(100_000), receivedBy: $this->user);

    $component = Livewire::test(Agents::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->assertSeeInOrder(['Safari Tours', 'Coastal Travel']);

    [$safariRow, $coastalRow] = $component->instance()->rows();

    expect($safariRow)
        ->bookings->toBe(2)
        ->lost->toBe(1)
        ->room_nights->toBe(4)
        ->and($safariRow['room_revenue']->toMajor())->toBe(400_000.0)
        ->and($safariRow['billed']->toMajor())->toBe(300_000.0)
        ->and($safariRow['paid']->toMajor())->toBe(100_000.0)
        ->and($safariRow['balance']->toMajor())->toBe(200_000.0)
        ->and($coastalRow)
        ->bookings->toBe(1)
        ->room_nights->toBe(2)
        ->and($coastalRow['billed']->isZero())->toBeTrue()
        ->and($component->instance()->totals()['room_revenue']->toMajor())->toBe(600_000.0);
});

it('blocks report pages for roles without access', function (string $role, string $routeName): void {
    actingAsTenantUser($this->hotel, User::factory()->create(), $role);

    $this->get(route($routeName))->assertForbidden();
})->with([
    ['staff', 'reports.cash-up'],
    ['waiter', 'reports.drinks-sales'],
    ['front_desk', 'reports.food-sales'],
    ['front_desk', 'reports.profit-loss'],
    ['cashier', 'reports.expenses'],
    ['cashier', 'reports.stock'],
    ['staff', 'reports.credit'],
    ['waiter', 'reports.extras-sales'],
    ['cashier', 'reports.agents'],
]);

it('renders every report page for an accountant', function (string $routeName): void {
    $this->get(route($routeName))->assertOk();
})->with(['reports.cash-up', 'reports.drinks-sales', 'reports.food-sales', 'reports.extras-sales', 'reports.stock', 'reports.expenses', 'reports.credit', 'reports.profit-loss', 'reports.agents']);

it('limits cash-up tabs to the payments views for a cashier', function (): void {
    actingAsTenantUser($this->hotel, User::factory()->create(), 'cashier');

    Livewire::test(CashUp::class, ['view' => 'reservations'])
        ->assertSet('view', 'staff')
        ->assertDontSee('In/Out Reservations')
        ->call('showView', 'reservations')
        ->assertSet('view', 'staff');
});

it('limits cash-up tabs to reservations for front desk', function (): void {
    actingAsTenantUser($this->hotel, User::factory()->create(), 'front_desk');

    Livewire::test(CashUp::class, ['view' => 'general'])
        ->assertSet('view', 'reservations')
        ->assertDontSee('General Payments');
});

it('adds a user with a role to an existing hotel from the command line', function (): void {
    Role::query()->where('name', 'accountant')->where('hotel_id', $this->hotel->id)->delete();

    $this->artisan('app:add-hotel-user', [
        'hotel' => $this->hotel->slug,
        'email' => 'books@example.test',
        '--role' => 'accountant',
        '--name' => 'Books Keeper',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    $user = User::query()->where('email', 'books@example.test')->sole();
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->hotel->id);

    expect($user->hotels->pluck('id')->all())->toBe([$this->hotel->id])
        ->and($user->hasRole('accountant'))->toBeTrue()
        ->and($user->can('view-reports'))->toBeTrue();
});

it('rejects unknown roles in the add-hotel-user command', function (): void {
    $this->artisan('app:add-hotel-user', [
        'hotel' => $this->hotel->slug,
        'email' => 'nobody@example.test',
        '--role' => 'wizard',
    ])->assertFailed();
});
