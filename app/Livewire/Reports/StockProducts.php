<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Domain\Stock\Actions\IssueStock;
use App\Domain\Stock\Actions\RecordStockEntry;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrderItem;
use App\Models\StockEntry;
use App\Models\StockIssue;
use App\Models\StockType;
use App\Models\Supplier;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use RuntimeException;

/**
 * Stock received in the period (the default tab), stock issued in the period
 * (transfers, internal use, wastage), and stock on hand for every
 * stock-tracked menu item with what came in, went out and was sold.
 */
#[Title('Stock Products Report')]
class StockProducts extends ReportComponent
{
    public const LOW_STOCK_LEVEL = 5;

    public const EXPIRY_WARNING_DAYS = 30;

    public const VIEWS = ['received', 'issued', 'on-hand'];

    #[Url]
    public string $outlet = '';

    #[Url(as: 'type')]
    public string $stockType = '';

    #[Url]
    public string $view = 'received';

    public string $entryItem = '';

    public string $entrySupplier = '';

    public string $entryDate = '';

    public string $entryExpiry = '';

    public string $entryDescription = '';

    public string $entryQuantity = '';

    public string $entryUnitCost = '';

    public function showView(string $view): void
    {
        $this->view = in_array($view, self::VIEWS, true) ? $view : 'received';
    }

    public function canRecord(): bool
    {
        return Auth::user()?->can('manage-stock') ?? false;
    }

    public function openCreate(): void
    {
        abort_unless($this->canRecord(), 403);

        $this->reset(['entryItem', 'entrySupplier', 'entryExpiry', 'entryDescription', 'entryQuantity', 'entryUnitCost']);
        $this->entryDate = CarbonImmutable::today()->toDateString();
        $this->resetValidation();
        Flux::modal('stock-entry-form')->show();
    }

    public function save(RecordStockEntry $recordStockEntry): void
    {
        abort_unless($this->canRecord(), 403);

        $validated = $this->validate([
            'entryItem' => ['required', Rule::in($this->menuItems()->modelKeys())],
            'entrySupplier' => ['nullable', Rule::in($this->suppliers()->modelKeys())],
            'entryDate' => ['required', 'date', 'before_or_equal:today'],
            'entryExpiry' => ['nullable', 'date', 'after_or_equal:entryDate'],
            'entryDescription' => ['nullable', 'string', 'max:255'],
            'entryQuantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'entryUnitCost' => ['required', 'numeric', 'min:0', 'max:1000000000'],
        ], attributes: [
            'entrySupplier' => __('supplier'),
            'entryUnitCost' => __('unit cost'),
        ]);

        $recordStockEntry->execute(
            item: $this->menuItems()->findOrFail((int) $validated['entryItem']),
            quantity: (int) $validated['entryQuantity'],
            entryDate: CarbonImmutable::parse($validated['entryDate']),
            expiryDate: $validated['entryExpiry'] ? CarbonImmutable::parse($validated['entryExpiry']) : null,
            description: $validated['entryDescription'] ?: null,
            recordedBy: Auth::user(),
            supplier: $validated['entrySupplier'] ? $this->suppliers()->findOrFail((int) $validated['entrySupplier']) : null,
            unitCost: Money::fromMajor((float) $validated['entryUnitCost'])->amount,
        );

        Flux::modal('stock-entry-form')->close();
        Flux::toast(variant: 'success', text: __('Stock recorded.'));
        unset($this->entries, $this->rows, $this->totals, $this->menuItems);
    }

    public function delete(int $id, RecordStockEntry $recordStockEntry): void
    {
        abort_unless($this->canRecord(), 403);

        try {
            $recordStockEntry->reverse(StockEntry::query()->findOrFail($id));
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Stock entry deleted.'));
        unset($this->entries, $this->rows, $this->totals);
    }

    public function reverseIssue(int $id, IssueStock $issueStock): void
    {
        abort_unless($this->canRecord(), 403);

        try {
            $issueStock->reverse(StockIssue::query()->findOrFail($id));
        } catch (RuntimeException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Issue reversed.'));
        unset($this->issues, $this->rows, $this->totals);
    }

    /**
     * Active menu items stock can be received for, limited to the selected outlet.
     *
     * @return Collection<int, MenuItem>
     */
    #[Computed]
    public function menuItems(): Collection
    {
        return MenuItem::query()
            ->with('outlet')
            ->where('is_active', true)
            ->when($this->outlet !== '', fn (Builder $query) => $query->where('outlet_id', (int) $this->outlet))
            ->orderBy('outlet_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Supplier>
     */
    #[Computed]
    public function suppliers(): Collection
    {
        return Supplier::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return Collection<int, StockType>
     */
    #[Computed]
    public function stockTypes(): Collection
    {
        return StockType::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, StockEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        return StockEntry::query()
            ->with(['menuItem.outlet', 'supplier', 'recorder'])
            ->whereDate('entry_date', '>=', $this->periodStart())
            ->whereDate('entry_date', '<=', $this->periodEnd())
            ->whereHas('menuItem', fn (Builder $item) => $this->filterItems($item))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Stock issued from the filtered outlet's products in the period.
     *
     * @return Collection<int, StockIssue>
     */
    #[Computed]
    public function issues(): Collection
    {
        return StockIssue::query()
            ->with(['menuItem.outlet', 'toMenuItem.outlet', 'recorder'])
            ->whereDate('issue_date', '>=', $this->periodStart())
            ->whereDate('issue_date', '<=', $this->periodEnd())
            ->whereHas('menuItem', fn (Builder $item) => $this->filterItems($item))
            ->orderBy('issue_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Total cost of the entries in minor units, counting only entries with a cost.
     */
    public function entriesCost(): Money
    {
        return new Money((int) $this->entries()->sum(fn (StockEntry $entry): int => $entry->totalCost() ?? 0), $this->hotelCurrency());
    }

    /**
     * Whether an entry's expiry date has passed ('expired'), is close ('soon') or neither.
     */
    public function expiryState(StockEntry $entry): ?string
    {
        if ($entry->expiry_date === null) {
            return null;
        }

        $today = CarbonImmutable::today();

        return match (true) {
            $entry->expiry_date->lt($today) => 'expired',
            $entry->expiry_date->lte($today->addDays(self::EXPIRY_WARNING_DAYS)) => 'soon',
            default => null,
        };
    }

    /**
     * @return Collection<int, Outlet>
     */
    #[Computed]
    public function outlets(): Collection
    {
        return Outlet::query()->orderBy('name')->get();
    }

    /**
     * @return array<int, array{name: string, outlet: string, category: string, stock_type: string, price: Money, received: int, issued: int, sold: int, in_stock: int, value: Money, status: string}>
     */
    #[Computed]
    public function rows(): array
    {
        $items = MenuItem::query()
            ->with(['outlet', 'category', 'stockType'])
            ->where('track_stock', true)
            ->tap(fn (Builder $query) => $this->filterItems($query))
            ->orderBy('outlet_id')
            ->orderBy('name')
            ->get();

        $itemIds = $items->modelKeys();

        $sold = PosOrderItem::query()
            ->whereIn('menu_item_id', $itemIds)
            ->whereHas('order', fn (Builder $query) => $query
                ->where('status', 'billed')
                ->whereBetween('billed_at', [$this->periodStart(), $this->periodEnd()]))
            ->groupBy('menu_item_id')
            ->selectRaw('menu_item_id, SUM(quantity) as sold')
            ->pluck('sold', 'menu_item_id');

        $received = StockEntry::query()
            ->whereIn('menu_item_id', $itemIds)
            ->whereDate('entry_date', '>=', $this->periodStart())
            ->whereDate('entry_date', '<=', $this->periodEnd())
            ->groupBy('menu_item_id')
            ->selectRaw('menu_item_id, SUM(quantity) as received')
            ->pluck('received', 'menu_item_id');

        $issuesInPeriod = StockIssue::query()
            ->whereDate('issue_date', '>=', $this->periodStart())
            ->whereDate('issue_date', '<=', $this->periodEnd());

        $issued = (clone $issuesInPeriod)
            ->whereIn('menu_item_id', $itemIds)
            ->groupBy('menu_item_id')
            ->selectRaw('menu_item_id, SUM(quantity) as issued')
            ->pluck('issued', 'menu_item_id');

        $transferredIn = (clone $issuesInPeriod)
            ->whereIn('to_menu_item_id', $itemIds)
            ->groupBy('to_menu_item_id')
            ->selectRaw('to_menu_item_id, SUM(quantity) as received')
            ->pluck('received', 'to_menu_item_id');

        return $items->map(fn (MenuItem $item): array => [
            'name' => $item->name,
            'outlet' => $item->outlet->name,
            'category' => $item->category->name,
            'stock_type' => $item->stockType->name ?? '—',
            'price' => $item->price,
            'received' => (int) ($received[$item->id] ?? 0) + (int) ($transferredIn[$item->id] ?? 0),
            'issued' => (int) ($issued[$item->id] ?? 0),
            'sold' => (int) ($sold[$item->id] ?? 0),
            'in_stock' => $item->stock_quantity,
            'value' => $item->price->multiply(max($item->stock_quantity, 0)),
            'status' => match (true) {
                $item->stock_quantity <= 0 => 'Out of Stock',
                $item->stock_quantity <= self::LOW_STOCK_LEVEL => 'Low Stock',
                default => 'In Stock',
            },
        ])->all();
    }

    /**
     * @return array{products: int, low: int, out: int, value: Money}
     */
    #[Computed]
    public function totals(): array
    {
        $rows = $this->rows();

        return [
            'products' => count($rows),
            'low' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'Low Stock')),
            'out' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'Out of Stock')),
            'value' => array_reduce($rows, fn (Money $carry, array $row): Money => $carry->add($row['value']), Money::zero()),
        ];
    }

    public function render(): View
    {
        if (! in_array($this->view, self::VIEWS, true)) {
            $this->view = 'received';
        }

        return view('livewire.reports.stock-products');
    }

    protected function reportKey(): string
    {
        return 'stock';
    }

    /**
     * Narrows a menu item query to the selected outlet and stock type.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function filterItems(Builder $query): Builder
    {
        return $query
            ->when($this->outlet !== '', fn (Builder $query) => $query->where('outlet_id', (int) $this->outlet))
            ->when($this->stockType !== '', fn (Builder $query) => $query->where('stock_type_id', (int) $this->stockType));
    }
}
