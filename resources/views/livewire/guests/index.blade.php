<section class="w-full">
    <div class="pb-6">
        <flux:heading size="xl">{{ __('Guests') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Pick an available room to start a new reservation.') }}</flux:text>
    </div>

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
                        'occupied' => 'bg-blue-500',
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
                    @elseif ($isOccupiedWithFolio)
                        <a href="{{ route('folios.show', $activeFolio) }}"
                            wire:navigate
                            class="block w-full transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900"
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
                </div>
            @endforeach
        </div>
    @endif
</section>
