<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.cube class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Extra services') }}</flux:heading>

            <button type="button" wire:click="openCreate"
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('New extra service') }}
            </button>

            <a href="{{ route('extras.create') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Add Extras') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('extras.create') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Add extras') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('extras.orders') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Extra orders') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Price') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->services as $service)
                    <flux:table.row :key="$service->id">
                        <flux:table.cell variant="strong">{{ $service->name }}</flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $service->description ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $service->price->format() }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($service->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $service->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger"
                                        wire:click="delete({{ $service->id }})"
                                        wire:confirm="{{ __('Delete this extra service?') }}">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-500">
                            {{ __('No extra services yet. Add things like laundry, airport transfer or spa.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="extra-service-form" class="md:w-[480px]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId ? __('Edit extra service') : __('New extra service') }}
            </flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required autofocus />
            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />
            <flux:input type="number" min="0" step="any" wire:model="price"
                :label="__('Price')" :description="__('Default price; staff can adjust it per order.')" required />
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
