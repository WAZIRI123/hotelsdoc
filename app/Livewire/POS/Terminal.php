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
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Title('POS terminal')]
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
        $this->startNewOrder();
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
            ->whereIn('status', ['billed', 'sent', 'voided'])
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

    public function startNewOrder(): void
    {
        $order = PosOrder::query()->create([
            'outlet_id' => $this->outlet->id,
            'opened_by' => Auth::id(),
            'table_no' => $this->tableNo ?: null,
            'status' => 'open',
        ]);

        $this->orderId = $order->id;
        $this->tableNo = '';
        unset($this->order);
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
        $item = MenuItem::query()->where('outlet_id', $this->outlet->id)->findOrFail($menuItemId);
        $order = $this->order();
        if ($order === null) {
            return;
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

        app(RecalculatePosOrderTotals::class)->execute($order);
        unset($this->order);
    }

    public function changeQty(int $itemId, int $delta): void
    {
        $item = PosOrderItem::query()->findOrFail($itemId);
        $newQty = $item->quantity + $delta;

        if ($newQty <= 0) {
            $item->delete();
        } else {
            $item->update([
                'quantity' => $newQty,
                'line_total' => $item->unit_price->multiply($newQty),
            ]);
        }

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

        $order->update(['status' => 'voided']);
        Flux::toast(variant: 'success', text: 'Order voided.');
        $this->orderId = null;
        $this->startNewOrder();
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
        $this->orderId = null;
        $this->startNewOrder();
    }

    public function settleDirect(BillPosOrderToFolio $billAction, RecordPayment $payAction): void
    {
        $order = $this->order();
        if ($order === null || $this->settleMethod === '') {
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
            $folio = $billAction->settleAsWalkIn($order);
            $payAction->execute(
                folio: $folio,
                methodCode: $this->settleMethod,
                amount: $folio->total_amount,
                reference: $this->settleReference ?: null,
                receivedBy: Auth::user(),
            );
        } catch (RuntimeException|\InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Payment received.');
        Flux::modal('pos-settle')->close();
        $this->orderId = null;
        $this->startNewOrder();
    }

    public function render(): View
    {
        return view('livewire.pos.terminal');
    }
}
