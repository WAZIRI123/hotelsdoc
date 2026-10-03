<?php

declare(strict_types=1);

namespace App\Livewire\POS;

use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Payments\PaymentGatewayManager;
use App\Domain\POS\Actions\BillPosOrderToFolio;
use App\Domain\POS\Actions\RecalculatePosOrderTotals;
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
    public Outlet $outlet;

    public ?int $orderId = null;

    public string $tableNo = '';

    /* Settle modal state */
    public string $settleMode = 'walk_in';   // 'walk_in' | 'room'

    public string $settleMethod = '';

    public string $settleReference = '';

    public ?int $billToStayId = null;

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
        $item = MenuItem::query()->where('outlet_id', $this->outlet->id)->where('is_active', true)->findOrFail($menuItemId);
        $order = $this->order();

        if ($order !== null && ! $this->orderIsEditable($order)) {
            return;
        }

        if ($item->track_stock && $item->stock_quantity < 1) {
            Flux::toast(variant: 'danger', text: "{$item->name} is out of stock.");

            return;
        }

        $order ??= $this->openOrder();

        DB::transaction(function () use ($item, $order): void {
            if ($item->track_stock) {
                $item->decrement('stock_quantity');
            }

            $existing = $order->items->first(fn (PosOrderItem $i) => $i->menu_item_id === $item->id);

            if ($existing !== null) {
                $existing->update([
                    'quantity' => $existing->quantity + 1,
                    'line_total' => $existing->unit_price->multiply($existing->quantity + 1),
                ]);
            } else {
                $order->items()->create([
                    'menu_item_id' => $item->id,
                    'name_snapshot' => $item->name,
                    'quantity' => 1,
                    'unit_price' => $item->price,
                    'tax_amount' => Money::zero(),
                    'line_total' => $item->price,
                ]);
            }
        });

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

        DB::transaction(function () use ($item, $newQty, $delta, $menuItem): void {
            if ($menuItem?->track_stock) {
                if ($delta > 0) {
                    $menuItem->decrement('stock_quantity', $delta);
                } elseif ($delta < 0) {
                    $restore = $newQty <= 0 ? $item->quantity : abs($delta);
                    $menuItem->increment('stock_quantity', $restore);
                }
            }

            if ($newQty <= 0) {
                $item->delete();
            } else {
                $item->update([
                    'quantity' => $newQty,
                    'line_total' => $item->unit_price->multiply($newQty),
                ]);
            }
        });

        $order = $this->order();
        if ($order !== null) {
            app(RecalculatePosOrderTotals::class)->execute($order);
        }
        unset($this->order);
    }

    public function voidOrder(): void
    {
        $order = $this->order();
        if ($order === null) {
            return;
        }

        if (! $this->orderIsEditable($order)) {
            return;
        }

        DB::transaction(function () use ($order): void {
            foreach ($order->items as $line) {
                if ($line->menuItem?->track_stock) {
                    $line->menuItem->increment('stock_quantity', $line->quantity);
                }
            }
            $order->update(['status' => 'voided']);
        });

        Flux::toast(variant: 'success', text: 'Order voided.');
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
        // Default to "Bill to room" only when there's an in-house guest to bill to.
        $this->settleMode = $this->inHouseStays()->isEmpty() ? 'walk_in' : 'room';
        Flux::modal('pos-settle')->show();
    }

    public function billToRoom(BillPosOrderToFolio $billAction): void
    {
        $order = $this->order();
        if ($order === null) {
            return;
        }

        if ($this->billToStayId === null) {
            Flux::toast(variant: 'danger', text: 'Pick a guest.');

            return;
        }

        $stay = Stay::query()->whereNull('checked_out_at')->findOrFail($this->billToStayId);
        $folio = $stay->folio;
        if ($folio === null) {
            Flux::toast(variant: 'danger', text: 'Selected stay has no open folio.');

            return;
        }

        try {
            $billAction->execute($order, $folio);
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: "Billed to {$stay->guest?->fullName()}.");
        Flux::modal('pos-settle')->close();
        $this->finishOrder();
    }

    public function settleDirect(BillPosOrderToFolio $billAction, RecordPayment $payAction): void
    {
        $order = $this->order();
        if ($order === null || $this->settleMethod === '') {
            return;
        }

        if (! $this->paymentMethods()->contains('code', $this->settleMethod)) {
            Flux::toast(variant: 'danger', text: 'Pick a payment method.');

            return;
        }

        $needsRef = false;
        try {
            $needsRef = app(PaymentGatewayManager::class)->for($this->settleMethod)->requiresReference();
        } catch (\Throwable) {
            // unknown method handled below by RecordPayment
        }

        if ($needsRef && trim($this->settleReference) === '') {
            Flux::toast(variant: 'danger', text: 'Reference is required for this method.');

            return;
        }

        try {
            // One transaction: if the payment fails the order is not left billed onto an unpaid walk-in folio.
            DB::transaction(function () use ($billAction, $payAction, $order): void {
                $folio = $billAction->settleAsWalkIn($order);
                $payAction->execute(
                    folio: $folio,
                    methodCode: $this->settleMethod,
                    amount: $folio->total_amount,
                    reference: $this->settleReference ?: null,
                    receivedBy: Auth::user(),
                );
            });
        } catch (RuntimeException|\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $printUrl = route('pos.transactions.print', $order->fresh());

        Flux::toast(variant: 'success', text: 'Payment received.');
        Flux::modal('pos-settle')->close();
        $this->dispatch('open-print', url: $printUrl);
        $this->finishOrder();
    }

    public function render(): View
    {
        return view('livewire.pos.terminal');
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
