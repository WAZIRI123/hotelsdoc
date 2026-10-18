<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Expense;
use App\Models\ExtraOrder;
use App\Models\FolioCharge;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\StockEntry;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

/**
 * Income (room charges, POS and extras sales, other folio charges, net of
 * discounts and VAT) against recorded expenses and stock purchases for the
 * period. A hotel runs in a single currency, so every row is counted; none
 * are filtered out.
 *
 * Stock is costed when it is received (quantity × unit cost on the entry
 * date), so wastage and internal use are already inside Stock Purchases.
 */
#[Title('Profit & Loss Report')]
class ProfitLoss extends ReportComponent
{
    /**
     * Folio charge sources that are reported on their own line or are not income.
     *
     * @var list<string>
     */
    private const SEPARATELY_REPORTED_SOURCES = ['room', 'pos_order', 'extra_order', 'discount', 'tax'];

    /**
     * Outlet types whose POS sales count as food & drinks income.
     *
     * @var array<string, list<string>>
     */
    private const POS_SALES_OUTLET_TYPES = [
        'drinks' => ['bar'],
        'food' => ['restaurant', 'room_service'],
        'shop' => ['shop'],
    ];

    /**
     * @return array<string, Money>
     */
    #[Computed]
    public function income(): array
    {
        return [
            __('Room Revenue') => $this->folioCharges(fn (Builder $query) => $query->where('source_type', 'room')),
            __('Drinks Sales') => $this->posSales('drinks'),
            __('Food Sales') => $this->posSales('food'),
            __('Shop Sales') => $this->posSales('shop'),
            __('Extras Sales') => $this->money((int) ExtraOrder::query()
                ->whereDoesntHave('folio', fn (Builder $folio) => $folio->where('status', 'void'))
                ->whereDate('order_date', '>=', $this->periodStart())
                ->whereDate('order_date', '<=', $this->periodEnd())
                ->sum(DB::raw('total_amount - tax_amount'))),
            __('Other Charges') => $this->folioCharges(fn (Builder $query) => $query->whereNotIn('source_type', self::SEPARATELY_REPORTED_SOURCES)),
            __('Less: Discounts') => $this->folioDiscounts()->subtract($this->posDiscounts()),
        ];
    }

    /**
     * Discounts given on guest bills (a negative amount, before VAT).
     */
    #[Computed]
    public function folioDiscounts(): Money
    {
        return $this->folioCharges(fn (Builder $query) => $query->where('source_type', 'discount'));
    }

    /**
     * What line discounts and price cuts at the till took off billed POS
     * sales, before VAT (a positive amount).
     */
    #[Computed]
    public function posDiscounts(): Money
    {
        return $this->sum(array_map(
            fn (string $group): Money => $this->posDiscountsFor($group),
            array_keys(self::POS_SALES_OUTLET_TYPES),
        ));
    }

    /**
     * Income grouped into the report's three sales columns, each after its
     * discounts. Shop sales and till discounts count with food & drinks;
     * other folio charges and bill discounts with accommodation.
     *
     * @return array{food_drinks: Money, accommodation: Money, extras: Money}
     */
    #[Computed]
    public function salesColumns(): array
    {
        $income = $this->income();

        return [
            'food_drinks' => $this->sum([$income[__('Drinks Sales')], $income[__('Food Sales')], $income[__('Shop Sales')]])->subtract($this->posDiscounts()),
            'accommodation' => $this->sum([$income[__('Room Revenue')], $income[__('Other Charges')], $this->folioDiscounts()]),
            'extras' => $income[__('Extras Sales')],
        ];
    }

    #[Computed]
    public function totalIncome(): Money
    {
        return $this->sum($this->income());
    }

    /**
     * Recorded expenses by category, plus stock received in the period.
     *
     * @return array<string, Money>
     */
    #[Computed]
    public function expenses(): array
    {
        $expenses = Expense::query()
            ->whereDate('expense_date', '>=', $this->periodStart())
            ->whereDate('expense_date', '<=', $this->periodEnd())
            ->groupBy('category')
            ->selectRaw('category, SUM(amount) as total')
            ->orderByDesc('total')
            ->pluck('total', 'category')
            ->map(fn (mixed $total): Money => $this->money((int) $total))
            ->all();

        $stockPurchases = $this->stockPurchases();

        if ($stockPurchases->isPositive()) {
            $expenses[__('Stock Purchases')] = $stockPurchases;
            uasort($expenses, fn (Money $a, Money $b): int => $b->amount <=> $a->amount);
        }

        return $expenses;
    }

    /**
     * Cost of stock received in the period: quantity × unit cost per entry.
     */
    #[Computed]
    public function stockPurchases(): Money
    {
        return $this->money((int) StockEntry::query()
            ->whereNotNull('unit_cost')
            ->whereDate('entry_date', '>=', $this->periodStart())
            ->whereDate('entry_date', '<=', $this->periodEnd())
            ->sum(DB::raw('quantity * unit_cost')));
    }

    #[Computed]
    public function totalExpenses(): Money
    {
        return $this->sum($this->expenses());
    }

    #[Computed]
    public function netProfit(): Money
    {
        return $this->totalIncome()->subtract($this->totalExpenses());
    }

    public function render(): View
    {
        return view('livewire.reports.profit-loss');
    }

    protected function reportKey(): string
    {
        return 'profit-loss';
    }

    /**
     * @param  callable(Builder<FolioCharge>): Builder<FolioCharge>  $scope
     */
    private function folioCharges(callable $scope): Money
    {
        $query = FolioCharge::query()
            ->whereBetween('posted_at', [$this->periodStart(), $this->periodEnd()])
            ->whereHas('folio', fn (Builder $folio) => $folio->where('status', '!=', 'void'));

        return $this->money((int) $scope($query)->sum(DB::raw('total - tax_amount')));
    }

    /**
     * Billed POS sales before tax and before till discounts for one group of
     * outlet types; the discounts are taken off under Less: Discounts.
     */
    private function posSales(string $group): Money
    {
        return $this->money((int) $this->billedPosOrders($group)->sum('subtotal'))
            ->add($this->posDiscountsFor($group));
    }

    /**
     * Till discounts on one group's billed POS sales, before VAT.
     */
    private function posDiscountsFor(string $group): Money
    {
        return $this->money((int) PosOrderItem::query()
            ->whereIn('pos_order_id', $this->billedPosOrders($group)->select('id'))
            ->sum('amount_off_net'));
    }

    /**
     * @return Builder<PosOrder>
     */
    private function billedPosOrders(string $group): Builder
    {
        return PosOrder::query()
            ->where('status', 'billed')
            ->whereBetween('billed_at', [$this->periodStart(), $this->periodEnd()])
            ->whereHas('outlet', fn (Builder $query) => $query->whereIn('type', self::POS_SALES_OUTLET_TYPES[$group]));
    }

    private function money(int $amount): Money
    {
        return new Money($amount, $this->hotelCurrency());
    }

    /**
     * @param  array<array-key, Money>  $lines
     */
    private function sum(array $lines): Money
    {
        return array_reduce($lines, fn (Money $carry, Money $line): Money => $carry->add($line), $this->money(0));
    }
}
