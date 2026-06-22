<section class="w-full">
    <div class="flex items-center justify-between gap-4 pb-6">
        <div>
            <flux:heading size="xl">{{ __('Room types') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Categories of rooms with their default rates.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            {{ __('New room type') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Code') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Occupancy') }}</flux:table.column>
            <flux:table.column>{{ __('Default rate') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->roomTypes as $type)
                <flux:table.row :key="$type->id">
                    <flux:table.cell class="font-mono text-sm">{{ $type->code }}</flux:table.cell>
                    <flux:table.cell variant="strong">{{ $type->name }}</flux:table.cell>
                    <flux:table.cell>{{ $type->base_occupancy }} / {{ $type->max_occupancy }}</flux:table.cell>
                    <flux:table.cell>{{ $type->default_rate->format() }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($type->is_active)
                            <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:dropdown align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $type->id }})">
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger"
                                    wire:click="delete({{ $type->id }})"
                                    wire:confirm="{{ __('Delete this room type?') }}">
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">
                        {{ __('No room types yet. Create your first one.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="room-type-form" class="md:w-[480px]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? __('Edit room type') : __('New room type') }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ __('Codes like STD, DLX, SUITE. Keep them short.') }}
                </flux:text>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="code" :label="__('Code')" required autofocus />
                <flux:input wire:model="name" :label="__('Name')" required />
            </div>

            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model.number="base_occupancy" type="number" min="1" max="20" :label="__('Base occupancy')" required />
                <flux:input wire:model.number="max_occupancy" type="number" min="1" max="20" :label="__('Max occupancy')" required />
            </div>

            <flux:input wire:model.number="default_rate_tsh" type="number" min="0"
                :label="__('Default rate (TSh per night)')" required />

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
