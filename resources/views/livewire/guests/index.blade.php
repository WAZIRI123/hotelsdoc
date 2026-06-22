<section class="w-full">
    <div class="flex items-center justify-between gap-4 pb-6">
        <div>
            <flux:heading size="xl">{{ __('Guests') }}</flux:heading>
            <flux:text class="mt-1">{{ __('People who have stayed or have an upcoming reservation.') }}</flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search…') }}" />
            <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('New guest') }}</flux:button>
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Phone') }}</flux:table.column>
            <flux:table.column>{{ __('Email') }}</flux:table.column>
            <flux:table.column>{{ __('Nationality') }}</flux:table.column>
            <flux:table.column>{{ __('ID') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->guests as $guest)
                <flux:table.row :key="$guest->id">
                    <flux:table.cell variant="strong">{{ $guest->fullName() }}</flux:table.cell>
                    <flux:table.cell>{{ $guest->phone ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $guest->email ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $guest->nationality ?? '—' }}</flux:table.cell>
                    <flux:table.cell class="font-mono text-xs">
                        {{ $guest->id_number ? str_replace('_', ' ', $guest->id_type ?? '').' '.$guest->id_number : '—' }}
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:dropdown align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $guest->id }})">
                                    {{ __('Edit') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="text-center text-zinc-500">
                        {{ __('No guests yet.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->guests->links() }}
    </div>

    <flux:modal name="guest-form" class="md:w-[560px]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId ? __('Edit guest') : __('New guest') }}
            </flux:heading>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="first_name" :label="__('First name')" required autofocus />
                <flux:input wire:model="last_name" :label="__('Last name')" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="phone" :label="__('Phone')" />
                <flux:input wire:model="email" type="email" :label="__('Email')" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="nationality" :label="__('Nationality')" />
                <flux:select wire:model="id_type" :label="__('ID type')">
                    <flux:select.option value="">—</flux:select.option>
                    <flux:select.option value="passport">{{ __('Passport') }}</flux:select.option>
                    <flux:select.option value="national_id">{{ __('National ID') }}</flux:select.option>
                    <flux:select.option value="drivers_license">{{ __('Driver\'s license') }}</flux:select.option>
                </flux:select>
            </div>

            <flux:input wire:model="id_number" :label="__('ID number')" />
            <flux:textarea wire:model="address" :label="__('Address')" rows="2" />
            <flux:textarea wire:model="notes" :label="__('Notes')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
