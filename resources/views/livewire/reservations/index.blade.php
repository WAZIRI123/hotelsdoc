<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.calendar-days class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Reservations') }}</flux:heading>

            <a href="{{ route('reservations.create') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('New reservation') }}
            </a>

            <a href="{{ route('guests.index') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Guest Book') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="#" wire:click.prevent="$set('filterStatus', 'all')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('All') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#" wire:click.prevent="$set('filterStatus', 'confirmed')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Confirmed') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#" wire:click.prevent="$set('filterStatus', 'checked_in')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Checked in') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#" wire:click.prevent="$set('filterStatus', 'checked_out')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Checked out') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#" wire:click.prevent="$set('filterStatus', 'cancelled')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Cancelled') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#" wire:click.prevent="$set('filterStatus', 'no_show')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('No show') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <div class="flex items-center justify-end pb-4">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="{{ __('Confirmation no, guest…') }}" />
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
    </div>
</section>
