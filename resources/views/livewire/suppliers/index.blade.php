<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.truck class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('View Supplier') }}</flux:heading>

            <a href="{{ route('suppliers.create', array_filter(['outlet' => $outletId])) }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Add Supplier') }}
            </a>
            <a href="{{ $outletId ? route('manage-stock.outlet', $outletId) : route('manage-stock') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Manage Stock') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('suppliers.create', array_filter(['outlet' => $outletId])) }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Add Supplier') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('stock-types.index', array_filter(['outlet' => $outletId])) }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('View Stock Type') }}</a>
            @can('view-reports')
                <span class="text-zinc-400 dark:text-zinc-600">|</span>
                <a href="{{ route('reports.stock', array_filter(['outlet' => $outletId])) }}" wire:navigate
                    class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Stock Received') }}</a>
            @endcan
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <div class="flex flex-wrap items-center justify-between gap-3 pb-4">
            <flux:text>{{ __('Vendors the hotel buys stock from. Pick one when recording stock received.') }}</flux:text>
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="{{ __('Name, contact, phone, email, TIN…') }}" />
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Contact person') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('TIN') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Deliveries') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->suppliers as $supplier)
                    <flux:table.row :key="$supplier->id">
                        <flux:table.cell variant="strong">
                            {{ $supplier->name }}
                            @if ($supplier->address)
                                <flux:text class="text-xs text-zinc-500">{{ $supplier->address }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $supplier->contact_person ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $supplier->phone ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $supplier->email ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $supplier->tin ?: '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $supplier->stock_entries_count }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($supplier->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" :href="route('suppliers.edit', ['supplier' => $supplier] + array_filter(['outlet' => $outletId]))" wire:navigate>
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger"
                                        wire:click="delete({{ $supplier->id }})"
                                        wire:confirm="{{ __('Delete this supplier? Its past deliveries stay on record without a supplier.') }}">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500">
                            {{ __('No suppliers yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $this->suppliers->links() }}
        </div>
    </div>
</section>
