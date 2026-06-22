<section class="w-full">
    <div class="flex items-center justify-between gap-4 pb-6">
        <div>
            <flux:heading size="xl">{{ __('Reservations') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Bookings, current stays, and history.') }}</flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="{{ __('Confirmation no, guest…') }}" />
            <flux:select wire:model.live="filterStatus" size="sm">
                <flux:select.option value="all">{{ __('All') }}</flux:select.option>
                <flux:select.option value="confirmed">{{ __('Confirmed') }}</flux:select.option>
                <flux:select.option value="checked_in">{{ __('Checked in') }}</flux:select.option>
                <flux:select.option value="checked_out">{{ __('Checked out') }}</flux:select.option>
                <flux:select.option value="cancelled">{{ __('Cancelled') }}</flux:select.option>
                <flux:select.option value="no_show">{{ __('No show') }}</flux:select.option>
            </flux:select>
            <flux:button variant="primary" icon="plus" :href="route('reservations.create')" wire:navigate>
                {{ __('New reservation') }}
            </flux:button>
        </div>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Conf #') }}</flux:table.column>
            <flux:table.column>{{ __('Guest') }}</flux:table.column>
            <flux:table.column>{{ __('Arrival') }}</flux:table.column>
            <flux:table.column>{{ __('Departure') }}</flux:table.column>
            <flux:table.column>{{ __('Rooms') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->reservations as $reservation)
                <flux:table.row :key="$reservation->id">
                    <flux:table.cell class="font-mono text-sm">{{ $reservation->confirmation_no }}</flux:table.cell>
                    <flux:table.cell variant="strong">{{ $reservation->guest?->fullName() }}</flux:table.cell>
                    <flux:table.cell>{{ $reservation->arrival->format('D, M j') }}</flux:table.cell>
                    <flux:table.cell>{{ $reservation->departure->format('D, M j') }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $reservation->rooms->map(fn ($r) => $r->room?->number)->filter()->implode(', ') ?: '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @php
                            $color = match ($reservation->status) {
                                'confirmed' => 'blue',
                                'checked_in' => 'green',
                                'checked_out' => 'zinc',
                                'cancelled' => 'red',
                                'no_show' => 'orange',
                                default => 'zinc',
                            };
                        @endphp
                        <flux:badge :color="$color" size="sm">
                            {{ str_replace('_', ' ', ucfirst($reservation->status)) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:button variant="ghost" size="sm" icon="arrow-right"
                            :href="route('reservations.show', $reservation)" wire:navigate />
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500">
                        {{ __('No reservations match those filters.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $this->reservations->links() }}
    </div>
</section>
