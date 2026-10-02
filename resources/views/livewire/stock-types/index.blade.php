<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.cube class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('View Stock Type') }}</flux:heading>

            <a href="{{ route('stock-types.create', array_filter(['outlet' => $outletId])) }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Add Stock Type') }}
            </a>
            <a href="{{ $outletId ? route('manage-stock.outlet', $outletId) : route('manage-stock') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Manage Stock') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('stock-types.create', array_filter(['outlet' => $outletId])) }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Add Stock Type') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('suppliers.index', array_filter(['outlet' => $outletId])) }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('View Supplier') }}</a>
            @if ($outletId)
                <span class="text-zinc-400 dark:text-zinc-600">|</span>
                <a href="{{ route('menu.index', $outletId) }}" wire:navigate
                    class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Products') }}</a>
            @endif
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <flux:text class="pb-4">{{ __('Group stock-tracked products (Beverages, Food, Consumables…). Set a product\'s stock type when adding or editing it on the outlet menu.') }}</flux:text>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Products') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->stockTypes as $stockType)
                    <flux:table.row :key="$stockType->id">
                        <flux:table.cell variant="strong">{{ $stockType->name }}</flux:table.cell>
                        <flux:table.cell>{{ $stockType->description ?: '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $stockType->menu_items_count }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" :href="route('stock-types.edit', ['stockType' => $stockType] + array_filter(['outlet' => $outletId]))" wire:navigate>
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger"
                                        wire:click="delete({{ $stockType->id }})"
                                        wire:confirm="{{ __('Delete this stock type? Its products keep their stock but lose the type.') }}">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">
                            {{ __('No stock types yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
