<section class="w-full">
    <div class="flex items-center justify-between gap-4 pb-6">
        <div>
            <flux:heading size="xl">{{ __('Outlets') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Bars, restaurants, and other points of sale.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('New outlet') }}</flux:button>
    </div>

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
