<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Domain\Folios\BillSection;
use App\Domain\Reports\FolioPaymentShare;
use App\Models\Outlet;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Billed POS sales for one family of outlets (bars for drinks, restaurants
 * for food), summarised per menu item or listed per order, plus the unbilled
 * orders cashiers cleared from the terminal.
 */
abstract class PosSales extends ReportComponent
{
    public const VIEWS = ['orders', 'items', 'cleared'];

    #[Url]
    public string $outlet = '';

    #[Url]
    public string $view = 'orders';

    /**
     * Outlet types (see Outlet::TYPES) whose sales belong to this report.
     *
     * @return list<string>
     */
    abstract public function outletTypes(): array;

    abstract public function heading(): string;

    abstract public function icon(): string;

    public function showView(string $view): void
    {
        $this->view = in_array($view, self::VIEWS, true) ? $view : 'orders';
    }

    /**
     * @return Collection<int, Outlet>
     */
    #[Computed]
    public function outlets(): Collection
    {
        return Outlet::query()
            ->whereIn('type', $this->outletTypes())
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, PosOrder>
     */
    #[Computed]
    public function orders(): Collection
    {
        return $this->billedOrders()
            ->with(['outlet', 'items', 'folio.guest', 'folio.payments', 'folio.charges'])
            ->orderBy('billed_at')
            ->get();
    }

    /**
     * Unbilled orders cleared from the terminal in the period, newest first.
     *
     * @return Collection<int, PosOrder>
     */
    #[Computed]
    public function clearedOrders(): Collection
    {
        return $this->outletOrders()
            ->where('status', 'cleared')
            ->whereBetween('cleared_at', [$this->periodStart(), $this->periodEnd()])
            ->with(['outlet', 'items', 'clearedBy'])
            ->latest('cleared_at')
            ->get();
    }

    /**
     * Quantity and revenue per menu item, best sellers first.
     *
     * @return list<array{name: string, outlet: string, quantity: int, total: Money}>
     */
    #[Computed]
    public function itemRows(): array
    {
        $rows = [];

        foreach ($this->orders() as $order) {
            foreach ($order->items as $item) {
                /** @var PosOrderItem $item */
                $rows[$item->menu_item_id] ??= ['name' => $item->name_snapshot, 'outlet' => $order->outlet->name, 'quantity' => 0, 'total' => Money::zero()];
                $rows[$item->menu_item_id]['quantity'] += $item->quantity;
                $rows[$item->menu_item_id]['total'] = $rows[$item->menu_item_id]['total']->add($item->line_total);
            }
        }

        usort($rows, fn (array $a, array $b): int => $b['total']->amount <=> $a['total']->amount);

        return $rows;
    }

    /**
     * One row per billed order with what was paid towards it.
     *
     * The discount is what line discounts and price cuts at the till took off
     * the bill (VAT included); see FolioPaymentShare for how payments are credited.
     *
     * @return array<int, array{order: PosOrder, customer: string|null, currency: string, total: Money, discount: Money, grand_total: Money, paid_by: string, paid: Money, balance: Money}>
     */
    #[Computed]
    public function orderRows(): array
    {
        return $this->orders()->map(function (PosOrder $order): array {
            $folio = $order->folio;
            $grandTotal = $order->total_amount;
            $paid = FolioPaymentShare::paid($folio, $grandTotal, BillSection::Outlets);
            $discount = $order->items->reduce(
                fn (Money $carry, PosOrderItem $item): Money => $carry->add($item->amount_off_gross),
                Money::zero(),
            );

            return [
                'order' => $order,
                'customer' => $folio?->guest?->fullName(),
                'currency' => $folio === null ? $this->hotelCurrency() : $folio->currency,
                'total' => $grandTotal->add($discount),
                'discount' => $discount,
                'grand_total' => $grandTotal,
                'paid_by' => FolioPaymentShare::paidBy($folio, BillSection::Outlets),
                'paid' => $paid,
                'balance' => $grandTotal->subtract($paid),
            ];
        })->all();
    }

    /**
     * @return array{orders: int, quantity: int, tax: Money, total: Money, discount: Money, paid: Money, balance: Money}
     */
    #[Computed]
    public function totals(): array
    {
        $orders = $this->orders();

        return [
            'orders' => $orders->count(),
            'quantity' => array_sum(array_column($this->itemRows(), 'quantity')),
            'tax' => Money::fromMinor((int) $orders->sum(fn (PosOrder $order): int => $order->tax_total->amount)),
            'total' => Money::fromMinor((int) $orders->sum(fn (PosOrder $order): int => $order->total_amount->amount)),
            'discount' => array_reduce($this->orderRows(), fn (Money $carry, array $row): Money => $carry->add($row['discount']), Money::zero()),
            'paid' => array_reduce($this->orderRows(), fn (Money $carry, array $row): Money => $carry->add($row['paid']), Money::zero()),
            'balance' => array_reduce($this->orderRows(), fn (Money $carry, array $row): Money => $carry->add($row['balance']), Money::zero()),
        ];
    }

    public function render(): View
    {
        if (! in_array($this->view, self::VIEWS, true)) {
            $this->view = 'orders';
        }

        return view('livewire.reports.pos-sales');
    }

    /**
     * @return Builder<PosOrder>
     */
    private function billedOrders(): Builder
    {
        return $this->outletOrders()
            ->where('status', 'billed')
            ->whereBetween('billed_at', [$this->periodStart(), $this->periodEnd()]);
    }

    /**
     * Orders from this report's outlets, narrowed to the chosen outlet.
     *
     * @return Builder<PosOrder>
     */
    private function outletOrders(): Builder
    {
        return PosOrder::query()
            ->whereHas('outlet', fn (Builder $query) => $query->whereIn('type', $this->outletTypes()))
            ->when($this->outlet !== '', fn (Builder $query) => $query->where('outlet_id', (int) $this->outlet));
    }
}
