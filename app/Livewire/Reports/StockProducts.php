<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Domain\Stock\Actions\RecordStockEntry;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrderItem;
use App\Models\StockEntry;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;

/**
 * Stock received in the period (the default tab), and stock on hand for
 * every stock-tracked menu item with what was sold in the period.
 */
#[Title('Stock Products Report')]
class StockProducts extends ReportComponent
{
    public const LOW_STOCK_LEVEL = 5;

    public const EXPIRY_WARNING_DAYS = 30;

    public const VIEWS = ['received', 'on-hand'];

    #[Url]
    public string $outlet = '';

    #[Url]
    public string $view = 'received';

    public string $entryItem = '';

    public string $entryDate = '';

    public string $entryExpiry = '';

    public string $entryDescription = '';

    public string $entryQuantity = '';

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

        $this->reset(['entryItem', 'entryExpiry', 'entryDescription', 'entryQuantity']);
        $this->entryDate = CarbonImmutable::today()->toDateString();
        $this->resetValidation();
        Flux::modal('stock-entry-form')->show();
    }

    public function save(RecordStockEntry $recordStockEntry): void
    {
        abort_unless($this->canRecord(), 403);

        $validated = $this->validate([
            'entryItem' => ['required', Rule::in($this->menuItems()->modelKeys())],
            'entryDate' => ['required', 'date', 'before_or_equal:today'],
            'entryExpiry' => ['nullable', 'date', 'after_or_equal:entryDate'],
            'entryDescription' => ['nullable', 'string', 'max:255'],
            'entryQuantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $recordStockEntry->execute(
            item: $this->menuItems()->findOrFail((int) $validated['entryItem']),
            quantity: (int) $validated['entryQuantity'],
            entryDate: CarbonImmutable::parse($validated['entryDate']),
            expiryDate: $validated['entryExpiry'] ? CarbonImmutable::parse($validated['entryExpiry']) : null,
            description: $validated['entryDescription'] ?: null,
            recordedBy: Auth::user(),
        );

        Flux::modal('stock-entry-form')->close();
        Flux::toast(variant: 'success', text: __('Stock recorded.'));
        unset($this->entries, $this->rows, $this->totals, $this->menuItems);
    }

    public function delete(int $id, RecordStockEntry $recordStockEntry): void
    {
        abort_unless($this->canRecord(), 403);

        $recordStockEntry->reverse(StockEntry::query()->findOrFail($id));

        Flux::toast(variant: 'success', text: __('Stock entry deleted.'));
        unset($this->entries, $this->rows, $this->totals);
    }

    /**
     * Active menu items stock can be received for, grouped by outlet in the form.
     *
     * @return Collection<int, MenuItem>
     */
    #[Computed]
    public function menuItems(): Collection
    {
        return MenuItem::query()
            ->with('outlet')
            ->where('is_active', true)
            ->orderBy('outlet_id')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, StockEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        return StockEntry::query()
            ->with(['menuItem.outlet', 'recorder'])
            ->whereDate('entry_date', '>=', $this->periodStart())
            ->whereDate('entry_date', '<=', $this->periodEnd())
            ->when($this->outlet !== '', fn (Builder $query) => $query->whereHas(
                'menuItem',
                fn (Builder $item) => $item->where('outlet_id', (int) $this->outlet),
            ))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();
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
     * @return array<int, array{name: string, outlet: string, category: string, price: Money, in_stock: int, sold: int, value: Money, status: string}>
     */
    #[Computed]
    public function rows(): array
    {
        $items = MenuItem::query()
            ->with(['outlet', 'category'])
            ->where('track_stock', true)
            ->when($this->outlet !== '', fn (Builder $query) => $query->where('outlet_id', (int) $this->outlet))
            ->orderBy('outlet_id')
            ->orderBy('name')
            ->get();

        $sold = PosOrderItem::query()
            ->whereIn('menu_item_id', $items->modelKeys())
            ->whereHas('order', fn (Builder $query) => $query
                ->where('status', 'billed')
                ->whereBetween('billed_at', [$this->periodStart(), $this->periodEnd()]))
            ->groupBy('menu_item_id')
            ->selectRaw('menu_item_id, SUM(quantity) as sold')
            ->pluck('sold', 'menu_item_id');

        return $items->map(fn (MenuItem $item): array => [
            'name' => $item->name,
            'outlet' => $item->outlet->name,
            'category' => $item->category->name,
            'price' => $item->price,
            'in_stock' => $item->stock_quantity,
            'sold' => (int) ($sold[$item->id] ?? 0),
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
}
