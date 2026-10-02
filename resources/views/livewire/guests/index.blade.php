<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.information-circle class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Current rooms status') }}</flux:heading>

            <a href="{{ route('guests.register') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Register Guest') }}
            </a>

            <a href="{{ route('guests.list') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('View Guests') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('reservations.create') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Book Room') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#reservations"
                x-on:click.prevent="$wire.set('filterStatus', 'all'); document.getElementById('reservations').scrollIntoView({behavior: 'smooth', block: 'start'})"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('All') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#reservations"
                x-on:click.prevent="$wire.set('filterStatus', 'confirmed'); document.getElementById('reservations').scrollIntoView({behavior: 'smooth', block: 'start'})"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Confirmed') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#reservations"
                x-on:click.prevent="$wire.set('filterStatus', 'checked_in'); document.getElementById('reservations').scrollIntoView({behavior: 'smooth', block: 'start'})"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Checked in') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#reservations"
                x-on:click.prevent="$wire.set('filterStatus', 'checked_out'); document.getElementById('reservations').scrollIntoView({behavior: 'smooth', block: 'start'})"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Checked out') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#reservations"
                x-on:click.prevent="$wire.set('filterStatus', 'cancelled'); document.getElementById('reservations').scrollIntoView({behavior: 'smooth', block: 'start'})"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Cancelled') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#reservations"
                x-on:click.prevent="$wire.set('filterStatus', 'no_show'); document.getElementById('reservations').scrollIntoView({behavior: 'smooth', block: 'start'})"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('No show') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        @if ($this->rooms->isEmpty())
            <div class="rounded-lg border border-dashed border-zinc-200 p-12 text-center text-zinc-500 dark:border-zinc-700">
                {{ __('No rooms yet.') }}
                <flux:link :href="route('rooms.index')" wire:navigate>{{ __('Add some in Rooms.') }}</flux:link>
            </div>
        @else
            <div class="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($this->rooms as $room)
                    @php
                        $isAvailable = $room->status === 'available';
                        $activeFolio = $this->activeFolios[$room->id] ?? null;
                        $isOccupiedWithFolio = $room->status === 'occupied' && $activeFolio !== null;
                        $cardClasses = match ($room->status) {
                            'available' => 'bg-green-600',
                            'occupied' => 'bg-red-600',
                            'cleaning' => 'bg-yellow-500',
                            'maintenance' => 'bg-orange-500',
                            'out_of_order' => 'bg-red-600',
                            default => 'bg-zinc-500',
                        };
                        $statusLabel = strtoupper(str_replace('_', '-', $room->status));
                        $roomLabel = trim($room->number.' '.($room->roomType?->name ?? ''));
                    @endphp

                    <div class="flex flex-col items-center gap-2">
                        @if ($isAvailable)
                            <a href="{{ route('reservations.create', ['room' => $room->id]) }}"
                                wire:navigate
                                class="block w-full transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900"
                                aria-label="{{ __('Reserve room') }} {{ $roomLabel }}">
                                <x-room-status-card :class="$cardClasses" :label="$statusLabel" />
                            </a>
                        @elseif ($room->status === 'cleaning')
                            <button type="button" wire:click="markClean({{ $room->id }})"
                                wire:confirm="{{ __('Mark room :number as clean and available?', ['number' => $room->number]) }}"
                                class="block w-full transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900"
                                title="{{ __('Mark clean') }}"
                                aria-label="{{ __('Mark room clean') }} {{ $roomLabel }}">
                                <x-room-status-card :class="$cardClasses" :label="$statusLabel" />
                            </button>
                        @elseif ($isOccupiedWithFolio)
                            <a href="{{ route('folios.show', $activeFolio) }}"
                                wire:navigate
                                class="block w-full transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900"
                                aria-label="{{ __('Open folio for room') }} {{ $roomLabel }}">
                                <x-room-status-card :class="$cardClasses" :label="$statusLabel" />
                            </a>
                        @else
                            <div class="block w-full cursor-not-allowed"
                                title="{{ $statusLabel }}"
                                aria-disabled="true">
                                <x-room-status-card :class="$cardClasses" :label="$statusLabel" />
                            </div>
                        @endif

                        <flux:text class="text-center text-sm font-medium">{{ $roomLabel }}</flux:text>

                        @if ($room->roomType)
                            <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                                title="{{ __('Max :adults adults, :children children', ['adults' => $room->roomType->max_adults, 'children' => $room->roomType->max_children]) }}">
                                <flux:icon.users variant="micro" class="size-3.5" />
                                {{ trans_choice('{1} :count guest|[2,*] :count guests', $room->roomType->max_occupancy, ['count' => $room->roomType->max_occupancy]) }}
                                <span class="text-zinc-400 dark:text-zinc-500">·</span>
                                {{ $room->roomType->max_adults }}A {{ $room->roomType->max_children }}C
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <a href="{{ route('rooms.index') }}" wire:navigate
        class="mt-3 flex w-full flex-col items-center justify-center gap-2 rounded-lg bg-[#F3F3F3] px-6 py-4 text-zinc-500 transition hover:text-zinc-700 focus:outline-none focus:ring-2 focus:ring-zinc-400 focus:ring-offset-2 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200 dark:focus:ring-offset-zinc-900">
        <flux:icon.plus class="size-7" />
        <span class="text-sm font-medium">{{ __('Add Room') }}</span>
    </a>

    <div id="reservations" class="mt-3 scroll-mt-4 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center justify-between gap-4 pb-6">
            <div>
                <flux:heading size="lg">{{ __('Reservations') }}</flux:heading>
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
    </div>
</section>
