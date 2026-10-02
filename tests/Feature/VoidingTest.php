<?php

declare(strict_types=1);

use App\Domain\Folios\Actions\PostFolioCharge;
use App\Domain\Folios\Actions\VoidFolio;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Payments\Actions\VoidPayment;
use App\Domain\POS\Actions\BillPosOrderToFolio;
use App\Domain\POS\Actions\RecalculatePosOrderTotals;
use App\Livewire\Folios\Show as FolioShow;
use App\Livewire\POS\Transaction;
use App\Livewire\Reports\CashUp;
use App\Livewire\Reports\ProfitLoss;
use App\Models\ExtraOrder;
use App\Models\FiscalReceipt;
use App\Models\Folio;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Support\Money;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->outlet = Outlet::factory()->for($this->hotel)->create(['type' => 'bar']);
    $category = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();
    $this->beer = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($category, 'category')->create([
        'price' => 5_000_00,
        'track_stock' => true,
        'stock_quantity' => 8,
    ]);

    $this->billedOrder = function (int $quantity, Folio $folio): PosOrder {
        $order = PosOrder::factory()->for($this->hotel)->create(['outlet_id' => $this->outlet->id]);
        $order->items()->create([
            'hotel_id' => $this->hotel->id,
            'menu_item_id' => $this->beer->id,
            'name_snapshot' => 'Beer',
            'quantity' => $quantity,
            'unit_price' => 5_000_00,
            'line_total' => $quantity * 5_000_00,
        ]);
        $this->beer->decrement('stock_quantity', $quantity);
        app(RecalculatePosOrderTotals::class)->execute($order);

        return app(BillPosOrderToFolio::class)->execute($order->fresh(), $folio);
    };
});

it('voids a payment so it leaves the cash-up and reopens the folio', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'status' => 'open', 'subtotal' => 50_000_00, 'total_amount' => 50_000_00]);
    $payment = app(RecordPayment::class)->execute($folio, 'cash', Money::fromMajor(50_000), receivedBy: $this->user);
    expect($folio->fresh()->status)->toBe('settled');

    Livewire::test(FolioShow::class, ['folio' => $folio->fresh()])
        ->call('startVoidPayment', $payment->id)
        ->set('void_reason', 'Wrong guest')
        ->call('voidPayment')
        ->assertHasNoErrors();

    $folio->refresh();
    expect($payment->fresh()->status)->toBe('voided')
        ->and($payment->fresh()->void_reason)->toBe('Wrong guest')
        ->and($folio->paid_total->amount)->toBe(0)
        ->and($folio->status)->toBe('open')
        ->and(FiscalReceipt::query()->where('folio_id', $folio->id)->where('status', 'issued')->exists())->toBeFalse()
        ->and(Livewire::test(CashUp::class, ['view' => 'general'])->instance()->grandTotal()->amount)->toBe(0);
});

it('requires a reason to void a payment', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'status' => 'open', 'total_amount' => 50_000_00]);
    $payment = app(RecordPayment::class)->execute($folio, 'cash', Money::fromMajor(10_000), receivedBy: $this->user);

    app(VoidPayment::class)->execute($payment, ' ');
})->throws(RuntimeException::class, 'reason');

it('voids a billed POS order: credits its folio and returns the stock', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'status' => 'open']);
    app(PostFolioCharge::class)->price($folio, 'room', null, 'Room', Money::fromMajor(100_000));
    $order = ($this->billedOrder)(2, $folio->fresh());
    expect($folio->fresh()->total_amount->amount)->toBe(110_000_00)
        ->and($this->beer->fresh()->stock_quantity)->toBe(6);

    Livewire::test(Transaction::class, ['posOrder' => $order])
        ->assertSee('Void order')
        ->call('startVoid')
        ->set('void_reason', 'Rang up twice')
        ->call('voidOrder')
        ->assertHasNoErrors();

    expect($order->fresh()->status)->toBe('voided')
        ->and($folio->fresh()->total_amount->amount)->toBe(100_000_00)
        ->and($this->beer->fresh()->stock_quantity)->toBe(8);
});

it('refuses to void a folio that still holds payments', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'status' => 'open', 'total_amount' => 50_000_00]);
    app(RecordPayment::class)->execute($folio, 'cash', Money::fromMajor(10_000), receivedBy: $this->user);

    app(VoidFolio::class)->execute($folio->fresh(), 'Mistake', $this->user);
})->throws(RuntimeException::class, 'holds payments');

it('voids a folio, its POS orders and keeps its sales out of the reports', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'status' => 'open']);
    $order = ($this->billedOrder)(3, $folio);
    ExtraOrder::factory()->for($this->hotel)->create([
        'folio_id' => $folio->id,
        'order_date' => today()->toDateString(),
        'total_amount' => 20_000_00,
        'tax_amount' => 0,
    ]);

    Livewire::test(FolioShow::class, ['folio' => $folio->fresh()])
        ->call('startVoidFolio')
        ->set('void_reason', 'Opened for the wrong guest')
        ->call('voidFolio')
        ->assertHasNoErrors()
        ->assertSee('Folio voided');

    $income = Livewire::test(ProfitLoss::class)->instance()->income();

    expect($folio->fresh()->status)->toBe('void')
        ->and($order->fresh()->status)->toBe('voided')
        ->and($this->beer->fresh()->stock_quantity)->toBe(8)
        ->and($income[__('Drinks Sales')]->amount)->toBe(0)
        ->and($income[__('Extras Sales')]->amount)->toBe(0);
});
