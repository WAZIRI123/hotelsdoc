<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Domain\Reports\FolioPaymentShare;
use App\Models\ExtraOrder;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

#[Title('Extras Sales Report')]
class ExtrasSales extends ReportComponent
{
    public const VIEWS = ['orders', 'items'];

    #[Url]
    public string $view = 'orders';

    public function showView(string $view): void
    {
        $this->view = in_array($view, self::VIEWS, true) ? $view : 'orders';
    }

    /**
     * @return Collection<int, ExtraOrder>
     */
    #[Computed]
    public function orders(): Collection
    {
        return ExtraOrder::query()
            ->with(['items', 'stay.guest', 'folio.payments', 'creator'])
            ->whereDate('order_date', '>=', $this->periodStart())
            ->whereDate('order_date', '<=', $this->periodEnd())
            ->orderBy('order_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array{name: string, quantity: int, total: Money}>
     */
    #[Computed]
    public function itemRows(): array
    {
        $rows = [];

        foreach ($this->orders() as $order) {
            foreach ($order->items as $item) {
                $rows[$item->extra_service_id] ??= ['name' => $item->name_snapshot, 'quantity' => 0, 'total' => Money::zero($this->hotelCurrency())];
                $rows[$item->extra_service_id]['quantity'] += $item->quantity;
                $rows[$item->extra_service_id]['total'] = $rows[$item->extra_service_id]['total']->add(new Money((int) $item->line_total, $this->hotelCurrency()));
            }
        }

        usort($rows, fn (array $a, array $b): int => $b['total']->amount <=> $a['total']->amount);

        return $rows;
    }

    /**
     * One row per order with what was paid towards it.
     *
     * Extras carry no discount. Orders are credited per FolioPaymentShare;
     * legacy walk-in orders recorded before walk-ins were paid through a folio
     * have none, so they count as paid in full.
     *
     * @return array<int, array{order: ExtraOrder, customer: string|null, currency: string, total: Money, discount: Money, grand_total: Money, paid_by: string, paid: Money, balance: Money}>
     */
    #[Computed]
    public function orderRows(): array
    {
        return $this->orders()->map(function (ExtraOrder $order): array {
            $grandTotal = $order->total();
            $paid = $order->folio === null ? $grandTotal : FolioPaymentShare::paid($order->folio, $grandTotal);

            return [
                'order' => $order,
                'customer' => $order->stay?->guest?->fullName(),
                'currency' => $order->currency,
                'total' => $grandTotal,
                'discount' => Money::zero($order->currency),
                'grand_total' => $grandTotal,
                'paid_by' => $order->folio === null ? __('Counter') : FolioPaymentShare::paidBy($order->folio),
                'paid' => $paid,
                'balance' => $grandTotal->subtract($paid),
            ];
        })->all();
    }

    /**
     * @return array{orders: int, walk_in: int, quantity: int, total: Money, paid: Money, balance: Money}
     */
    #[Computed]
    public function totals(): array
    {
        $orders = $this->orders();

        return [
            'orders' => $orders->count(),
            'walk_in' => $orders->filter(fn (ExtraOrder $order): bool => $order->isWalkIn())->count(),
            'quantity' => array_sum(array_column($this->itemRows(), 'quantity')),
            'total' => new Money((int) $orders->sum('total_amount'), $this->hotelCurrency()),
            'paid' => array_reduce($this->orderRows(), fn (Money $carry, array $row): Money => $carry->add($row['paid']), Money::zero($this->hotelCurrency())),
            'balance' => array_reduce($this->orderRows(), fn (Money $carry, array $row): Money => $carry->add($row['balance']), Money::zero($this->hotelCurrency())),
        ];
    }

    public function render(): View
    {
        if (! in_array($this->view, self::VIEWS, true)) {
            $this->view = 'orders';
        }

        return view('livewire.reports.extras-sales');
    }

    protected function reportKey(): string
    {
        return 'extras';
    }
}
