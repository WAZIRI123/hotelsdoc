<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.building-storefront class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Outlets') }}</flux:heading>

            <button type="button" wire:click="openCreate"
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('New outlet') }}
            </button>

            <a href="{{ route('pos.lobby') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Open POS') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('pos.lobby') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Point of Sale') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('reports.cash-up') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Cash-up report') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->outlets as $outlet)
                <flux:table.row :key="$outlet->id">
                    <flux:table.cell variant="strong">{{ $outlet->name }}</flux:table.cell>
                    <flux:table.cell>{{ ucwords(str_replace('_', ' ', $outlet->type)) }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($outlet->is_active)
                            <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:dropdown align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="rectangle-stack" :href="route('menu.index', $outlet)" wire:navigate>
                                    {{ __('Manage menu') }}
                                </flux:menu.item>
                                <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $outlet->id }})">
                                    {{ __('Edit') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center text-zinc-500">
                        {{ __('No outlets yet. Add a bar or restaurant to get started.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
    </div>

    <flux:modal name="outlet-form" class="md:w-[420px]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit outlet') : __('New outlet') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required autofocus />

            <flux:select wire:model="type" :label="__('Type')" required>
                <flux:select.option value="bar">{{ __('Bar') }}</flux:select.option>
                <flux:select.option value="restaurant">{{ __('Restaurant') }}</flux:select.option>
                <flux:select.option value="room_service">{{ __('Room service') }}</flux:select.option>
                <flux:select.option value="shop">{{ __('Shop') }}</flux:select.option>
            </flux:select>

            <flux:switch wire:model="is_active" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
