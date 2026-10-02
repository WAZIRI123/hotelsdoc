<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.rectangle-stack class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Room types') }}</flux:heading>

            <button type="button" wire:click="openCreate"
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('New room type') }}
            </button>

            <a href="{{ route('rooms.index') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('View Rooms') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('rooms.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Rooms') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('meal-plans.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Meal plans') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('rate-plans.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Add rates') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

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
    </div>

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
                :label="__('Default rate (:currency per night)', ['currency' => \App\Support\HotelCurrency::current()])" required />

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
