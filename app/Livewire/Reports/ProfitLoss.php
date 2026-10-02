<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\Expense;
use App\Models\ExtraOrder;
use App\Models\FolioCharge;
use App\Models\PosOrder;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;

/**
 * Income (room charges, POS and extras sales, other folio charges, net of
 * discounts and tax) against recorded expenses for the period, in the
 * hotel's currency.
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
     * @return array<string, Money>
     */
    #[Computed]
    public function income(): array
    {
        return [
            __('Room Revenue') => $this->folioCharges(fn (Builder $query) => $query->where('source_type', 'room')),
            __('Drinks Sales') => $this->posSales(['bar']),
            __('Food Sales') => $this->posSales(['restaurant', 'room_service']),
            __('Shop Sales') => $this->posSales(['shop']),
            __('Extras Sales') => $this->money((int) ExtraOrder::query()
                ->where('currency', $this->hotelCurrency())
                ->whereDate('order_date', '>=', $this->periodStart())
                ->whereDate('order_date', '<=', $this->periodEnd())
                ->sum('total_amount')),
            __('Other Charges') => $this->folioCharges(fn (Builder $query) => $query->whereNotIn('source_type', self::SEPARATELY_REPORTED_SOURCES)),
            __('Less: Discounts') => $this->folioCharges(fn (Builder $query) => $query->where('source_type', 'discount')),
        ];
    }

    /**
     * Income grouped into the report's three sales columns. Shop sales count
     * with food & drinks; other folio charges and discounts with accommodation.
     *
     * @return array{food_drinks: Money, accommodation: Money, extras: Money}
     */
    #[Computed]
    public function salesColumns(): array
    {
        $income = $this->income();

        return [
            'food_drinks' => $this->sum([$income[__('Drinks Sales')], $income[__('Food Sales')], $income[__('Shop Sales')]]),
            'accommodation' => $this->sum([$income[__('Room Revenue')], $income[__('Other Charges')], $income[__('Less: Discounts')]]),
            'extras' => $income[__('Extras Sales')],
        ];
    }

    #[Computed]
    public function totalIncome(): Money
    {
        return $this->sum($this->income());
    }

    /**
     * @return array<string, Money>
     */
    #[Computed]
    public function expenses(): array
    {
        return Expense::query()
            ->where('currency', $this->hotelCurrency())
            ->whereDate('expense_date', '>=', $this->periodStart())
            ->whereDate('expense_date', '<=', $this->periodEnd())
            ->groupBy('category')
            ->selectRaw('category, SUM(amount) as total')
            ->orderByDesc('total')
            ->pluck('total', 'category')
            ->map(fn (mixed $total): Money => $this->money((int) $total))
            ->all();
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
            ->whereHas('folio', fn (Builder $folio) => $folio
                ->where('currency', $this->hotelCurrency())
                ->where('status', '!=', 'void'));

        return $this->money((int) $scope($query)->sum('total'));
    }

    /**
     * Billed POS sales before tax for the given outlet types.
     *
     * @param  list<string>  $outletTypes
     */
    private function posSales(array $outletTypes): Money
    {
        return $this->money((int) PosOrder::query()
            ->where('status', 'billed')
            ->whereBetween('billed_at', [$this->periodStart(), $this->periodEnd()])
            ->whereHas('outlet', fn (Builder $query) => $query->whereIn('type', $outletTypes))
            ->sum('subtotal'));
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
