<?php

declare(strict_types=1);

namespace App\Livewire\Menu;

use App\Domain\Stock\Actions\RecordStockEntry;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemPriceChange;
use App\Models\Outlet;
use App\Models\StockType;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Title('Menu')]
class Index extends Component
{
    public Outlet $outlet;

    /* Category form */
    public ?int $editingCategoryId = null;

    #[Validate('required|string|max:80')]
    public string $categoryName = '';

    /* Item form */
    public ?int $editingItemId = null;

    #[Validate('required|integer|exists:menu_categories,id')]
    public ?int $itemCategoryId = null;

    #[Validate('required|string|max:120')]
    public string $itemName = '';

    #[Validate('nullable|string|max:300')]
    public string $itemDescription = '';

    /** Major units as typed, so fractional prices like 2.50 survive the round-trip. */
    #[Validate('required|numeric|min:0|decimal:0,2')]
    public string $itemPrice = '';

    public bool $itemIsActive = true;

    public bool $itemIsOpenPrice = false;

    public bool $itemTrackStock = false;

    /** Whether the item being edited already tracks stock, so its quantity only changes through stock movements. */
    public bool $itemWasTracked = false;

    public string $itemStockTypeId = '';

    #[Validate('required|integer|min:0')]
    public int $itemStockQuantity = 0;

    public function mount(Outlet $outlet): void
    {
        $this->outlet = $outlet;
    }

    /**
     * @return Collection<int, MenuCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return MenuCategory::query()
            ->where('outlet_id', $this->outlet->id)
            ->with(['items' => fn ($q) => $q->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
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
     * Past price edits of the item being edited, newest first.
     *
     * @return Collection<int, MenuItemPriceChange>
     */
    #[Computed]
    public function priceHistory(): Collection
    {
        if ($this->editingItemId === null) {
            return new Collection;
        }

        return MenuItemPriceChange::query()
            ->where('menu_item_id', $this->outletItem($this->editingItemId)->id)
            ->with('changedBy')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    /* Category actions */

    public function openCreateCategory(): void
    {
        $this->reset(['editingCategoryId', 'categoryName']);
        $this->resetValidation();
        Flux::modal('category-form')->show();
    }

    public function openEditCategory(int $id): void
    {
        $cat = MenuCategory::query()->where('outlet_id', $this->outlet->id)->findOrFail($id);
        $this->editingCategoryId = $cat->id;
        $this->categoryName = $cat->name;
        $this->resetValidation();
        Flux::modal('category-form')->show();
    }

    public function saveCategory(): void
    {
        $this->validateOnly('categoryName');

        $attrs = [
            'outlet_id' => $this->outlet->id,
            'name' => $this->categoryName,
            'is_active' => true,
        ];

        if ($this->editingCategoryId) {
            MenuCategory::query()->where('outlet_id', $this->outlet->id)->findOrFail($this->editingCategoryId)->update($attrs);
        } else {
            MenuCategory::query()->create($attrs);
        }

        Flux::modal('category-form')->close();
        Flux::toast(variant: 'success', text: 'Category saved.');
        unset($this->categories);
    }

    public function deleteCategory(int $id): void
    {
        $category = MenuCategory::query()->where('outlet_id', $this->outlet->id)->findOrFail($id);

        if ($category->hasHistory()) {
            $category->update(['is_active' => false]);
            Flux::toast(text: __('Items in this category have sales or stock history, so it was deactivated instead of deleted.'));
        } else {
            $category->delete();
            Flux::toast(variant: 'success', text: 'Category deleted.');
        }
        unset($this->categories);
    }

    /* Item actions */

    public function openCreateItem(?int $categoryId = null): void
    {
        $this->reset(['editingItemId', 'itemName', 'itemDescription', 'itemPrice', 'itemIsOpenPrice', 'itemTrackStock', 'itemWasTracked', 'itemStockQuantity', 'itemStockTypeId']);
        $this->itemCategoryId = $categoryId;
        $this->itemIsActive = true;
        $this->resetValidation();
        Flux::modal('item-form')->show();
    }

    public function openEditItem(int $id): void
    {
        $item = $this->outletItem($id);
        $this->editingItemId = $item->id;
        $this->itemCategoryId = $item->menu_category_id;
        $this->itemName = $item->name;
        $this->itemDescription = $item->description ?? '';
        $this->itemPrice = (string) $item->price->toMajor();
        $this->itemIsActive = $item->is_active;
        $this->itemIsOpenPrice = $item->is_open_price;
        $this->itemTrackStock = $item->track_stock;
        $this->itemWasTracked = $item->track_stock;
        $this->itemStockQuantity = $item->stock_quantity;
        $this->itemStockTypeId = (string) ($item->stock_type_id ?? '');
        $this->resetValidation();
        Flux::modal('item-form')->show();
    }

    public function saveItem(RecordStockEntry $recordStockEntry): void
    {
        $this->validate([
            'itemCategoryId' => ['required', 'integer', Rule::exists('menu_categories', 'id')->where('outlet_id', $this->outlet->id)],
            'itemName' => 'required|string|max:120',
            'itemDescription' => 'nullable|string|max:300',
            'itemPrice' => 'required|numeric|min:0|decimal:0,2',
            'itemStockQuantity' => 'required|integer|min:0',
            'itemStockTypeId' => ['nullable', Rule::in($this->stockTypes()->modelKeys())],
        ]);

        $item = $this->editingItemId ? $this->outletItem($this->editingItemId) : null;

        if ($item?->track_stock && ! $this->itemTrackStock && $item->stock_quantity > 0) {
            $this->addError('itemTrackStock', __(':count still in stock. Issue them under Manage Stock before you stop tracking this item.', ['count' => $item->stock_quantity]));

            return;
        }

        // Stock only moves through receipts, issues and sales so the stock report stays accurate:
        // opening stock for a newly tracked item is recorded as a stock entry, not written directly.
        $isNewlyTracked = $this->itemTrackStock && $item?->track_stock !== true;
        $openingStock = $isNewlyTracked ? $this->itemStockQuantity : 0;
        $stockQuantity = $this->itemTrackStock && ! $isNewlyTracked ? $item->stock_quantity : 0;

        $attrs = [
            'outlet_id' => $this->outlet->id,
            'menu_category_id' => $this->itemCategoryId,
            'name' => $this->itemName,
            'description' => $this->itemDescription ?: null,
            'price' => Money::fromMajor((float) $this->itemPrice),
            'is_open_price' => $this->itemIsOpenPrice,
            'is_active' => $this->itemIsActive,
            'stock_type_id' => $this->itemStockTypeId !== '' ? (int) $this->itemStockTypeId : null,
            'track_stock' => $this->itemTrackStock,
            'stock_quantity' => $stockQuantity,
        ];

        DB::transaction(function () use ($item, $attrs, $openingStock, $recordStockEntry): void {
            if ($item) {
                $oldPrice = $item->price;
                $item->update($attrs);

                if (! $oldPrice->equals($item->price)) {
                    $item->priceChanges()->create([
                        'old_price' => $oldPrice,
                        'new_price' => $item->price,
                        'changed_by' => Auth::id(),
                    ]);
                }
            } else {
                $item = MenuItem::query()->create($attrs);
            }

            if ($openingStock > 0) {
                $recordStockEntry->execute(
                    item: $item,
                    quantity: $openingStock,
                    entryDate: CarbonImmutable::today(),
                    description: __('Opening stock'),
                    recordedBy: Auth::user(),
                );
            }
        });

        Flux::modal('item-form')->close();
        Flux::toast(variant: 'success', text: 'Item saved.');
        unset($this->categories);
    }

    public function deleteItem(int $id): void
    {
        $item = $this->outletItem($id);

        if ($item->hasHistory()) {
            $item->update(['is_active' => false]);
            Flux::toast(text: __('This item has sales or stock history, so it was deactivated instead of deleted.'));
        } else {
            $item->delete();
            Flux::toast(variant: 'success', text: 'Item deleted.');
        }
        unset($this->categories);
    }

    public function render(): View
    {
        return view('livewire.menu.index');
    }

    private function outletItem(int $id): MenuItem
    {
        return MenuItem::query()->where('outlet_id', $this->outlet->id)->findOrFail($id);
    }
}
