<?php

declare(strict_types=1);

namespace App\Livewire\POS;

use App\Domain\Folios\BillSection;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Payments\PaymentGatewayManager;
use App\Domain\Payments\Tender;
use App\Domain\POS\Actions\BillPosOrderToFolio;
use App\Domain\POS\Actions\RecalculatePosOrderTotals;
use App\Livewire\Concerns\TakesTender;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Stay;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Title('Point of Sale')]
class Terminal extends Component
{
    use TakesTender;

    public Outlet $outlet;

    public ?int $orderId = null;

    public string $tableNo = '';

    /* Settle modal state */
    public string $settleMode = 'walk_in';   // 'walk_in' | 'room'

    public string $settleMethod = '';

    public string $settleReference = '';

    public ?int $billToStayId = null;

    /** For an in-house guest: 'billing' posts to their bill, 'pay_now' also takes payment here. */
    public string $roomSettle = 'billing';

    /* Open-price modal state */
    public ?int $openPriceItemId = null;

    public string $openPrice = '';

    /* Line adjustment modal state (managers only) */
    public ?int $adjustingLineId = null;

    public string $adjustUnitPrice = '';

    public string $adjustDiscount = '';

    public string $adjustReason = '';

    public function mount(Outlet $outlet): void
    {
        $this->outlet = $outlet;
        $this->resumeOpenOrder();
    }

    /**
     * @return Collection<int, MenuCategory>
     */
    #[Computed]
    public function menu(): Collection
    {
        return MenuCategory::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('is_active', true)
            ->with(['items' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Whether this user may change a line's price or discount it at the till.
     */
    #[Computed]
    public function canAdjustPrices(): bool
    {
        return Auth::user()?->can('override-pos-prices') ?? false;
    }

    #[Computed]
    public function order(): ?PosOrder
    {
        if ($this->orderId === null) {
            return null;
        }

        return PosOrder::query()->with('items.menuItem')->find($this->orderId);
    }

    /**
     * @return Collection<int, PosOrder>
     */
    #[Computed]
    public function transactions(): Collection
    {
        return PosOrder::query()
            ->where('outlet_id', $this->outlet->id)
            ->whereIn('status', ['billed', 'voided'])
            ->with(['folio.guest'])
            ->withCount('items')
            ->latest('id')
            ->limit(20)
            ->get();
    }

    /**
     * @return Collection<int, Stay>
     */
    #[Computed]
    public function inHouseStays(): Collection
    {
        return Stay::query()
            ->whereNull('checked_out_at')
            ->with(['guest', 'folio', 'reservation.rooms.room'])
            ->orderBy('checked_in_at', 'desc')
            ->get();
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    #[Computed]
    public function paymentMethods(): Collection
    {
        return PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    /**
     * Pick up this cashier's unfinished order at this outlet, so leaving the
     * page never strands an order (and the stock its items hold).
     */
    public function resumeOpenOrder(): void
    {
        $order = PosOrder::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('opened_by', Auth::id())
            ->where('status', 'open')
            ->latest('id')
            ->first();

        $this->orderId = $order?->id;
        $this->tableNo = $order->table_no ?? '';
        unset($this->order);
    }

    /**
     * Clear the screen for the next customer. The order itself is created
     * when its first item is added, so no empty orders are left behind.
     */
    public function finishOrder(): void
    {
        $this->orderId = null;
        $this->tableNo = '';
        unset($this->order);
    }

    private function openOrder(): PosOrder
    {
        $order = PosOrder::query()->create([
            'outlet_id' => $this->outlet->id,
            'opened_by' => Auth::id(),
            'table_no' => $this->tableNo ?: null,
            'status' => 'open',
        ]);

        $this->orderId = $order->id;
        unset($this->order);

        return $order;
    }

    public function updatedTableNo(): void
    {
        $order = $this->order();
        if ($order !== null) {
            $order->update(['table_no' => $this->tableNo ?: null]);
        }
    }

    public function addItem(int $menuItemId): void
    {
        $item = $this->sellableItem($menuItemId);

        if ($item->is_open_price) {
            $this->openPriceItemId = $item->id;
            $this->openPrice = $item->price->isPositive() ? (string) $item->price->toMajor() : '';
            $this->resetValidation();
            Flux::modal('pos-open-price')->show();

            return;
        }

        $this->addUnit($item, $item->price);
    }

    /**
     * Ring up an open-price item at the price the cashier typed in.
     */
    public function confirmOpenPrice(): void
    {
        $this->validate([
            'openPriceItemId' => 'required|integer',
            'openPrice' => 'required|numeric|min:0|decimal:0,2',
        ]);

        $item = $this->sellableItem((int) $this->openPriceItemId);

        if (! $item->is_open_price) {
            return;
        }

        Flux::modal('pos-open-price')->close();
        $this->addUnit($item, Money::fromMajor((float) $this->openPrice));
        $this->reset(['openPriceItemId', 'openPrice']);
    }

    private function sellableItem(int $menuItemId): MenuItem
    {
        return MenuItem::query()->where('outlet_id', $this->outlet->id)->where('is_active', true)->findOrFail($menuItemId);
    }

    /**
     * Add one unit of an item at the given price. It tops up an existing line
     * only when that line is untouched and still at this exact price, so every
     * line has a single price: units rung up before a menu price change,
     * open-price entries and manager-adjusted lines each stay on their own.
     */
    private function addUnit(MenuItem $item, Money $price): void
    {
        $order = $this->order();

        if ($order !== null && ! $this->orderIsEditable($order)) {
            return;
        }

        if ($item->track_stock && $item->stock_quantity < 1) {
            Flux::toast(variant: 'danger', text: "{$item->name} is out of stock.");

            return;
        }

        $added = DB::transaction(function () use ($item, $price, &$order): bool {
            $taken = $item->track_stock ? 1 : 0;

            if ($taken > 0 && ! $this->takeStock($item, $taken)) {
                return false;
            }

            $order ??= $this->openOrder();

            $existing = $item->is_open_price ? null : $order->items->first(
                fn (PosOrderItem $line): bool => $line->menu_item_id === $item->id
                    && $line->unit_price->equals($price)
                    && ! $line->isAdjusted(),
            );

            if ($existing !== null) {
                $existing->update([
                    'quantity' => $existing->quantity + 1,
                    'stock_taken' => $existing->stock_taken + $taken,
                    'line_total' => $existing->totalFor($existing->quantity + 1),
                ]);
            } else {
                $order->items()->create([
                    'menu_item_id' => $item->id,
                    'name_snapshot' => $item->name,
                    'quantity' => 1,
                    'stock_taken' => $taken,
                    'unit_price' => $price,
                    'discount_amount' => Money::zero(),
                    'tax_amount' => Money::zero(),
                    'line_total' => $price,
                ]);
            }

            return true;
        });

        if (! $added) {
            Flux::toast(variant: 'danger', text: "{$item->name} is out of stock.");

            return;
        }

        app(RecalculatePosOrderTotals::class)->execute($order);
        unset($this->order);
    }

    public function changeQty(int $itemId, int $delta): void
    {
        $item = PosOrderItem::query()
            ->with(['menuItem', 'order'])
            ->where('pos_order_id', $this->orderId)
            ->findOrFail($itemId);
        $parentOrder = $item->order;

        if ($parentOrder === null || ! $this->orderIsEditable($parentOrder)) {
            return;
        }

        $newQty = $item->quantity + $delta;
        $menuItem = $item->menuItem;

        if ($delta > 0 && $menuItem?->track_stock && $menuItem->stock_quantity < $delta) {
            Flux::toast(variant: 'danger', text: "{$menuItem->name} is out of stock.");

            return;
        }

        $changed = DB::transaction(function () use ($item, $newQty, $delta, $menuItem): bool {
            $stockTaken = $item->stock_taken;

            if ($delta > 0 && $menuItem?->track_stock) {
                if (! $this->takeStock($menuItem, $delta)) {
                    return false;
                }

                $stockTaken += $delta;
            } elseif ($delta < 0) {
                $keep = min($stockTaken, max($newQty, 0));

                if ($menuItem?->track_stock && $stockTaken > $keep) {
                    $menuItem->increment('stock_quantity', $stockTaken - $keep);
                }

                $stockTaken = $keep;
            }

            if ($newQty <= 0) {
                $item->delete();
            } else {
                // A discount bigger than what is left of the line shrinks to fit it.
                $gross = $item->unit_price->multiply($newQty);
                $discount = $item->discount_amount->amount > $gross->amount ? $gross : $item->discount_amount;

                $item->update([
                    'quantity' => $newQty,
                    'stock_taken' => $stockTaken,
                    'discount_amount' => $discount,
                    'line_total' => $gross->subtract($discount),
                ]);
            }

            return true;
        });

        if (! $changed) {
            Flux::toast(variant: 'danger', text: "{$menuItem->name} is out of stock.");

            return;
        }

        $order = $this->order();
        if ($order !== null) {
            app(RecalculatePosOrderTotals::class)->execute($order);
        }
        unset($this->order);
    }

    public function openAdjustLine(int $lineId): void
    {
        abort_unless($this->canAdjustPrices(), 403);

        $line = PosOrderItem::query()->where('pos_order_id', $this->orderId)->findOrFail($lineId);

        $this->adjustingLineId = $line->id;
        $this->adjustUnitPrice = (string) $line->unit_price->toMajor();
        $this->adjustDiscount = $line->discount_amount->isPositive() ? (string) $line->discount_amount->toMajor() : '';
        $this->adjustReason = $line->adjustment_reason ?? '';
        $this->resetValidation();
        Flux::modal('pos-adjust-line')->show();
    }

    /**
     * Override a line's unit price and/or take an amount off it. The price it
     * was first rung up at is kept, with the reason and who changed it; putting
     * the line back to that price with no discount clears the adjustment.
     */
    public function saveLineAdjustment(): void
    {
        abort_unless($this->canAdjustPrices(), 403);

        $this->validate([
            'adjustUnitPrice' => 'required|numeric|min:0|decimal:0,2',
            'adjustDiscount' => 'nullable|numeric|min:0|decimal:0,2',
            'adjustReason' => 'nullable|string|max:200',
        ]);

        $line = PosOrderItem::query()->with('order')->where('pos_order_id', $this->orderId)->findOrFail($this->adjustingLineId);

        if ($line->order === null || ! $this->orderIsEditable($line->order)) {
            return;
        }

        $ringUpPrice = $line->original_unit_price ?? $line->unit_price;
        $unitPrice = Money::fromMajor((float) $this->adjustUnitPrice);
        $discount = Money::fromMajor((float) ($this->adjustDiscount ?: 0));
        $gross = $unitPrice->multiply($line->quantity);
        $isAdjusted = ! $unitPrice->equals($ringUpPrice) || $discount->isPositive();

        if ($discount->amount > $gross->amount) {
            $this->addError('adjustDiscount', __('The discount cannot be more than the line total of :amount.', ['amount' => $gross->formatExact()]));

            return;
        }

        if ($isAdjusted && trim($this->adjustReason) === '') {
            $this->addError('adjustReason', __('Give a reason for the change.'));

            return;
        }

        $line->update([
            'unit_price' => $unitPrice,
            'original_unit_price' => $unitPrice->equals($ringUpPrice) ? null : $ringUpPrice,
            'discount_amount' => $discount,
            'adjustment_reason' => $isAdjusted ? trim($this->adjustReason) : null,
            'adjusted_by' => $isAdjusted ? Auth::id() : null,
            'line_total' => $gross->subtract($discount),
        ]);

        app(RecalculatePosOrderTotals::class)->execute($line->order);
        unset($this->order);

        Flux::modal('pos-adjust-line')->close();
        $this->reset(['adjustingLineId', 'adjustUnitPrice', 'adjustDiscount', 'adjustReason']);
        Flux::toast(variant: 'success', text: __('Line updated.'));
    }

    /**
     * Throw away the unbilled order so the cashier can start over. Its stock
     * goes back on hand; the order and its lines are kept as a cleared record
     * (who, when, what) for managers to review. An order with no lines left
     * holds nothing worth reviewing, so it is simply deleted.
     */
    public function clearOrder(): void
    {
        $order = $this->order();
        if ($order === null) {
            return;
        }

        if (! $this->orderIsEditable($order)) {
            return;
        }

        DB::transaction(function () use ($order): void {
            if ($order->items->isEmpty()) {
                $order->delete();

                return;
            }

            $order->restoreStock();
            $order->update([
                'status' => 'cleared',
                'cleared_at' => now(),
                'cleared_by' => Auth::id(),
            ]);
        });

        Flux::toast(variant: 'success', text: __('Order cleared.'));
        $this->finishOrder();
    }

    public function openSettle(): void
    {
        $order = $this->order();
        if ($order === null || $order->items->isEmpty()) {
            Flux::toast(variant: 'danger', text: 'Add items before settling.');

            return;
        }

        $this->settleMethod = '';
        $this->settleReference = '';
        $this->billToStayId = null;
        $this->roomSettle = 'billing';
        $this->resetTender();
        // Default to "Bill to room" only when there's an in-house guest to bill to.
        $this->settleMode = $this->inHouseStays()->isEmpty() ? 'walk_in' : 'room';
        Flux::modal('pos-settle')->show();
    }

    public function billToRoom(BillPosOrderToFolio $billAction): void
    {
        $order = $this->order();
        $stay = $this->selectedStay();
        if ($order === null || $stay === null) {
            return;
        }

        try {
            $billAction->execute($order, $stay->folio);
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: "Billed to {$stay->guest?->fullName()}.");
        Flux::modal('pos-settle')->close();
        $this->finishOrder();
    }

    /**
     * An in-house guest pays at the counter: the order still goes on their
     * bill, under outlets, with the payment against it, so the bill shows it
     * as already paid.
     */
    public function payForGuest(BillPosOrderToFolio $billAction, RecordPayment $payAction): void
    {
        $order = $this->order();
        if ($order === null || ! $this->settlePaymentIsValid()) {
            return;
        }

        $stay = $this->selectedStay();
        if ($stay === null) {
            return;
        }

        $tender = $this->validatedTender(mustCover: true);

        try {
            DB::transaction(function () use ($billAction, $payAction, $order, $stay, $tender): void {
                $billed = $billAction->execute($order, $stay->folio);
                $payAction->execute(
                    folio: $stay->folio->fresh(),
                    methodCode: $this->settleMethod,
                    amount: $billed->total_amount,
                    reference: $this->settleReference ?: null,
                    receivedBy: Auth::user(),
                    section: BillSection::Outlets,
                    tender: $tender,
                );
            });
        } catch (RuntimeException|\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $printUrl = route('pos.transactions.print', $order->fresh());

        Flux::toast(variant: 'success', text: "Payment received from {$stay->guest?->fullName()}.".$this->changeNote($tender, $order));
        Flux::modal('pos-settle')->close();
        $this->dispatch('open-print', url: $printUrl);
        $this->finishOrder();
    }

    public function settleDirect(BillPosOrderToFolio $billAction, RecordPayment $payAction): void
    {
        $order = $this->order();
        if ($order === null || ! $this->settlePaymentIsValid()) {
            return;
        }

        $tender = $this->validatedTender(mustCover: true);

        try {
            // One transaction: if the payment fails the order is not left billed onto an unpaid walk-in folio.
            DB::transaction(function () use ($billAction, $payAction, $order, $tender): void {
                $folio = $billAction->settleAsWalkIn($order);
                $payAction->execute(
                    folio: $folio,
                    methodCode: $this->settleMethod,
                    amount: $folio->total_amount,
                    reference: $this->settleReference ?: null,
                    receivedBy: Auth::user(),
                    tender: $tender,
                );
            });
        } catch (RuntimeException|\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $printUrl = route('pos.transactions.print', $order->fresh());

        Flux::toast(variant: 'success', text: 'Payment received.'.$this->changeNote($tender, $order));
        Flux::modal('pos-settle')->close();
        $this->dispatch('open-print', url: $printUrl);
        $this->finishOrder();
    }

    public function render(): View
    {
        return view('livewire.pos.terminal');
    }

    /**
     * What the order has to be paid in full, in the hotel currency.
     */
    protected function tenderDue(): Money
    {
        return $this->order()->total_amount ?? Money::zero();
    }

    /**
     * " Change: TZS 5,000." when the customer handed over more than the order total.
     */
    private function changeNote(Tender $tender, PosOrder $order): string
    {
        $change = $tender->changeFor($order->fresh()->total_amount);

        return $change === null ? '' : ' '.__('Change: :amount.', ['amount' => $change->format()]);
    }

    /**
     * Take units off a product's stock in one conditional update, so two tills
     * selling the last unit at once cannot both succeed and drive it negative.
     */
    private function takeStock(MenuItem $item, int $quantity): bool
    {
        return MenuItem::query()
            ->whereKey($item->id)
            ->where('stock_quantity', '>=', $quantity)
            ->decrement('stock_quantity', $quantity) === 1;
    }

    /**
     * The in-house guest picked to bill, as long as they have a bill open.
     */
    private function selectedStay(): ?Stay
    {
        if ($this->billToStayId === null) {
            Flux::toast(variant: 'danger', text: 'Pick a guest.');

            return null;
        }

        $stay = Stay::query()->whereNull('checked_out_at')->findOrFail($this->billToStayId);

        if ($stay->folio === null) {
            Flux::toast(variant: 'danger', text: 'Selected stay has no open guest billing.');

            return null;
        }

        return $stay;
    }

    /**
     * Whether a payment method (and its reference, when needed) was given.
     */
    private function settlePaymentIsValid(): bool
    {
        if (! $this->paymentMethods()->contains('code', $this->settleMethod)) {
            Flux::toast(variant: 'danger', text: 'Pick a payment method.');

            return false;
        }

        $needsRef = false;
        try {
            $needsRef = app(PaymentGatewayManager::class)->for($this->settleMethod)->requiresReference();
        } catch (\Throwable) {
            // unknown method handled later by RecordPayment
        }

        if ($needsRef && trim($this->settleReference) === '') {
            Flux::toast(variant: 'danger', text: 'Reference is required for this method.');

            return false;
        }

        return true;
    }

    private function orderIsEditable(PosOrder $order): bool
    {
        if ($order->status === 'open') {
            return true;
        }

        Flux::toast(variant: 'danger', text: "Cannot modify a {$order->status} order.");

        return false;
    }
}
