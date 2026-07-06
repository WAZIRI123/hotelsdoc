<?php

declare(strict_types=1);

namespace App\Livewire\Menu;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
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

    #[Validate('required|integer|min:0')]
    public int $itemPriceTsh = 0;

    public bool $itemIsActive = true;

    public bool $itemTrackStock = false;

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

    /* Category actions */

    public function openCreateCategory(): void
    {
        $this->reset(['editingCategoryId', 'categoryName']);
        $this->resetValidation();
        Flux::modal('category-form')->show();
    }

    public function openEditCategory(int $id): void
    {
        $cat = MenuCategory::query()->findOrFail($id);
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
            MenuCategory::query()->findOrFail($this->editingCategoryId)->update($attrs);
        } else {
            MenuCategory::query()->create($attrs);
        }

        Flux::modal('category-form')->close();
        Flux::toast(variant: 'success', text: 'Category saved.');
        unset($this->categories);
    }

    public function deleteCategory(int $id): void
    {
        MenuCategory::query()->findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: 'Category deleted.');
        unset($this->categories);
    }

    /* Item actions */

    public function openCreateItem(?int $categoryId = null): void
    {
        $this->reset(['editingItemId', 'itemName', 'itemDescription', 'itemPriceTsh', 'itemTrackStock', 'itemStockQuantity']);
        $this->itemCategoryId = $categoryId;
        $this->itemIsActive = true;
        $this->resetValidation();
        Flux::modal('item-form')->show();
    }

    public function openEditItem(int $id): void
    {
        $item = MenuItem::query()->findOrFail($id);
        $this->editingItemId = $item->id;
        $this->itemCategoryId = $item->menu_category_id;
        $this->itemName = $item->name;
        $this->itemDescription = $item->description ?? '';
        $this->itemPriceTsh = (int) $item->price->toMajor();
        $this->itemIsActive = $item->is_active;
        $this->itemTrackStock = $item->track_stock;
        $this->itemStockQuantity = $item->stock_quantity;
        $this->resetValidation();
        Flux::modal('item-form')->show();
    }

    public function saveItem(): void
    {
        $this->validate([
            'itemCategoryId' => 'required|integer|exists:menu_categories,id',
            'itemName' => 'required|string|max:120',
            'itemDescription' => 'nullable|string|max:300',
            'itemPriceTsh' => 'required|integer|min:0',
            'itemStockQuantity' => 'required|integer|min:0',
        ]);

        $attrs = [
            'outlet_id' => $this->outlet->id,
            'menu_category_id' => $this->itemCategoryId,
            'name' => $this->itemName,
            'description' => $this->itemDescription ?: null,
            'price' => Money::fromMajor($this->itemPriceTsh),
            'is_active' => $this->itemIsActive,
            'track_stock' => $this->itemTrackStock,
            'stock_quantity' => $this->itemTrackStock ? $this->itemStockQuantity : 0,
        ];

        if ($this->editingItemId) {
            MenuItem::query()->findOrFail($this->editingItemId)->update($attrs);
        } else {
            MenuItem::query()->create($attrs);
        }

        Flux::modal('item-form')->close();
        Flux::toast(variant: 'success', text: 'Item saved.');
        unset($this->categories);
    }

    public function deleteItem(int $id): void
    {
        MenuItem::query()->findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: 'Item deleted.');
        unset($this->categories);
    }

    public function render(): View
    {
        return view('livewire.menu.index');
    }
}
