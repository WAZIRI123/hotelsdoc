<section class="w-full">
    <div class="flex items-center justify-between gap-4 pb-6">
        <div>
            <flux:text class="text-sm">
                <flux:link :href="route('outlets.index')" wire:navigate>{{ __('Outlets') }}</flux:link>
                / {{ $outlet->name }}
            </flux:text>
            <flux:heading size="xl">{{ __('Menu') }} — {{ $outlet->name }}</flux:heading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button variant="ghost" icon="folder-plus" wire:click="openCreateCategory">
                {{ __('New category') }}
            </flux:button>
            <flux:button variant="primary" icon="plus" wire:click="openCreateItem"
                :disabled="$this->categories->isEmpty()">
                {{ __('New item') }}
            </flux:button>
        </div>
    </div>

    @if ($this->categories->isEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Add a category first') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Menu items belong to a category (Drinks, Snacks, Mains, etc.).') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="space-y-6">
        @foreach ($this->categories as $category)
            <flux:card>
                <div class="mb-4 flex items-center justify-between">
                    <flux:heading size="lg">{{ $category->name }}</flux:heading>
                    <div class="flex items-center gap-2">
                        <flux:button variant="ghost" size="sm" icon="plus" wire:click="openCreateItem({{ $category->id }})">
                            {{ __('Add item') }}
                        </flux:button>
                        <flux:dropdown align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="openEditCategory({{ $category->id }})">
                                    {{ __('Rename') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger"
                                    wire:click="deleteCategory({{ $category->id }})"
                                    wire:confirm="{{ __('Delete this category and all its items?') }}">
                                    {{ __('Delete category') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>

                @if ($category->items->isEmpty())
                    <flux:text class="text-sm text-zinc-500">{{ __('No items in this category yet.') }}</flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Item') }}</flux:table.column>
                            <flux:table.column align="center">{{ __('Price') }}</flux:table.column>
                            <flux:table.column align="center">{{ __('Stock') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($category->items as $item)
                                <flux:table.row :key="$item->id">
                                    <flux:table.cell variant="strong">
                                        {{ $item->name }}
                                        @if ($item->description)
                                            <flux:text class="text-xs text-zinc-500">{{ $item->description }}</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="center" class="font-mono">{{ $item->price->format() }}</flux:table.cell>
                                    <flux:table.cell align="center">
                                        @if ($item->track_stock)
                                            @if ($item->stock_quantity <= 0)
                                                <flux:badge color="red" size="sm">{{ __('Out of stock') }}</flux:badge>
                                            @else
                                                <span class="font-mono">{{ $item->stock_quantity }}</span>
                                            @endif
                                        @else
                                            <flux:text class="text-xs text-zinc-500">—</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($item->is_active)
                                            <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                        @else
                                            <flux:badge color="zinc" size="sm">{{ __('Hidden') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell class="text-right">
                                        <flux:dropdown align="end">
                                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                            <flux:menu>
                                                <flux:menu.item icon="pencil-square" wire:click="openEditItem({{ $item->id }})">
                                                    {{ __('Edit') }}
                                                </flux:menu.item>
                                                <flux:menu.separator />
                                                <flux:menu.item icon="trash" variant="danger"
                                                    wire:click="deleteItem({{ $item->id }})"
                                                    wire:confirm="{{ __('Delete this item?') }}">
                                                    {{ __('Delete') }}
                                                </flux:menu.item>
                                            </flux:menu>
                                        </flux:dropdown>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        @endforeach
    </div>

    <flux:modal name="category-form" class="md:w-[400px]">
        <form wire:submit="saveCategory" class="space-y-6">
            <flux:heading size="lg">{{ $editingCategoryId ? __('Rename category') : __('New category') }}</flux:heading>
            <flux:input wire:model="categoryName" :label="__('Name')" required autofocus />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="item-form" class="md:w-[480px]">
        <form wire:submit="saveItem" class="space-y-6">
            <flux:heading size="lg">{{ $editingItemId ? __('Edit item') : __('New item') }}</flux:heading>

            <flux:select wire:model="itemCategoryId" :label="__('Category')" required>
                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                @foreach ($this->categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="itemName" :label="__('Name')" required />
            <flux:textarea wire:model="itemDescription" :label="__('Description')" rows="2" />
            <flux:input wire:model.number="itemPriceTsh" type="number" min="0" :label="__('Price (:currency)', ['currency' => \App\Support\HotelCurrency::current()])" required />
            <flux:switch wire:model.live="itemTrackStock" :label="__('Track stock')"
                :description="__('Block adding to orders once the counter hits zero.')" />
            @if ($itemTrackStock)
                @if ($itemWasTracked)
                    <flux:field>
                        <flux:label>{{ __('Stock on hand') }}</flux:label>
                        <flux:text class="font-mono">{{ $itemStockQuantity }}</flux:text>
                        <flux:description>{{ __('Change it with Receive Stock or Issue Products under Manage Stock.') }}</flux:description>
                    </flux:field>
                @else
                    <flux:input wire:model.number="itemStockQuantity" type="number" min="0"
                        :label="__('Opening stock')" required />
                @endif
                <flux:select wire:model="itemStockTypeId" :label="__('Stock type')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($this->stockTypes as $stockType)
                        <flux:select.option :value="$stockType->id">{{ $stockType->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
            <flux:switch wire:model="itemIsActive" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
