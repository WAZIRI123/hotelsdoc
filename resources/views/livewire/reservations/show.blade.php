<section class="w-full max-w-4xl">
    <div class="flex items-start justify-between pb-6">
        <div>
            <flux:text class="font-mono text-sm">{{ $reservation->confirmation_no }}</flux:text>
            <flux:heading size="xl">{{ $reservation->guest?->fullName() }}</flux:heading>
            <flux:text class="mt-1">
                {{ $reservation->arrival->format('D, M j, Y') }}
                → {{ $reservation->departure->format('D, M j, Y') }}
                ({{ $reservation->arrival->diffInDays($reservation->departure) }} {{ __('nights') }})
            </flux:text>
        </div>

        <div class="flex items-center gap-2">
            @php
                $color = match ($reservation->status) {
                    'confirmed' => 'blue', 'checked_in' => 'green', 'checked_out' => 'zinc',
                    'cancelled' => 'red', 'no_show' => 'orange', default => 'zinc',
                };
            @endphp
            <flux:badge :color="$color" size="lg">
                {{ str_replace('_', ' ', ucfirst($reservation->status)) }}
            </flux:badge>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-3">
        <flux:card class="md:col-span-2 space-y-4">
            <flux:heading size="lg">{{ __('Rooms') }}</flux:heading>

            <div class="space-y-3">
                @foreach ($reservation->rooms as $rr)
                    @php
                        $activeOccupants = $rr->occupants->whereNull('checked_out_at');
                        $formerOccupants = $rr->occupants->whereNotNull('checked_out_at');
                        $roomIsFull = $rr->guestCount() >= ($rr->roomType?->max_occupancy ?? 0);
                    @endphp
                    <div wire:key="reservation-room-{{ $rr->id }}" class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <flux:heading size="sm">
                                    {{ __('Room') }} {{ $rr->room?->number ?? '—' }}
                                    <flux:text class="inline">— {{ $rr->roomType?->name }}</flux:text>
                                </flux:heading>
                                <flux:text class="text-sm">
                                    {{ $rr->nightly_rate->format() }} / {{ __('night') }}
                                    · {{ trans_choice(':count adult|:count adults', $rr->adults) }}@if ($rr->children), {{ trans_choice(':count child|:count children', $rr->children) }}@endif
                                    · {{ __('sleeps :max', ['max' => $rr->roomType?->max_occupancy]) }}
                                </flux:text>
                            </div>

                            @if ($this->canChangeOccupancy)
                                <flux:button size="sm" variant="ghost" icon="arrows-right-left"
                                    wire:click="startMovingRoom({{ $rr->id }})">
                                    {{ __('Move room') }}
                                </flux:button>
                            @endif
                        </div>

                        <ul class="mt-3 divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($activeOccupants as $occupant)
                                <li wire:key="occupant-{{ $occupant->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                                    <div class="flex items-center gap-2">
                                        <flux:icon :name="$occupant->isChild() ? 'face-smile' : 'user'" variant="mini" class="text-zinc-400" />
                                        @if ($quickNamingOccupantId === $occupant->id)
                                            <div class="relative" x-on:keydown.escape="$wire.cancelQuickNaming()">
                                                <form wire:submit="saveQuickName" class="flex items-center gap-1">
                                                    <input wire:model.live.debounce.300ms="quickName" type="text" autofocus autocomplete="off"
                                                        placeholder="{{ __('Type full name, press Enter') }}"
                                                        class="w-56 rounded-md border border-zinc-300 px-2 py-1 text-sm dark:border-zinc-600 dark:bg-zinc-900" />
                                                    <select wire:model="quickGender" aria-label="{{ __('Gender') }}"
                                                        class="rounded-md border border-zinc-300 px-2 py-1 text-sm dark:border-zinc-600 dark:bg-zinc-900">
                                                        <option value="">{{ __('Gender') }}</option>
                                                        <option value="male">{{ __('Male') }}</option>
                                                        <option value="female">{{ __('Female') }}</option>
                                                    </select>
                                                    <flux:button size="xs" type="submit" variant="primary" icon="check" />
                                                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="cancelQuickNaming" />
                                                </form>
                                                @error('quickName') <flux:text class="mt-1 text-xs text-red-600">{{ $message }}</flux:text> @enderror
                                                @error('quickGender') <flux:text class="mt-1 text-xs text-red-600">{{ $message }}</flux:text> @enderror

                                                @if ($this->quickNameMatches->isNotEmpty())
                                                    <div class="absolute z-10 mt-1 w-80 rounded-md border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                                        <div class="px-3 pt-2 text-xs text-zinc-500">{{ __('Already registered?') }}</div>
                                                        @foreach ($this->quickNameMatches as $match)
                                                            @php $staysIn = $this->quickNameConflicts[$match->id] ?? null; @endphp
                                                            @if ($staysIn)
                                                                <div wire:key="quick-match-{{ $match->id }}"
                                                                    title="{{ __('Already staying elsewhere for these dates') }}"
                                                                    class="flex w-full cursor-not-allowed items-center justify-between gap-2 px-3 py-2 text-sm text-zinc-400">
                                                                    <span class="font-medium">{{ $match->fullName() }}</span>
                                                                    <flux:badge size="sm" color="orange">{{ __('In house: :where', ['where' => $staysIn]) }}</flux:badge>
                                                                </div>
                                                            @else
                                                                <button type="button" wire:key="quick-match-{{ $match->id }}"
                                                                    wire:click="pickQuickNameMatch({{ $match->id }})"
                                                                    class="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">
                                                                    <span class="font-medium">{{ $match->fullName() }}</span>
                                                                    <span class="text-xs text-zinc-500">{{ $match->phone ?? $match->id_number ?? '' }}</span>
                                                                </button>
                                                            @endif
                                                        @endforeach
                                                        <div class="border-t border-zinc-100 px-3 py-2 text-xs text-zinc-500 dark:border-zinc-800">{{ __('Press Enter to register as a new guest.') }}</div>
                                                    </div>
                                                @endif
                                            </div>
                                        @elseif ($this->canNameOccupants && $occupant->guest_id !== $reservation->guest_id)
                                            <button type="button" wire:click="startQuickNaming({{ $occupant->id }})"
                                                title="{{ $occupant->guest === null ? __('Click to type a name') : __('Click to correct the name') }}"
                                                @class([
                                                    'cursor-text rounded border-b border-dashed text-sm hover:text-zinc-800 dark:hover:text-zinc-200',
                                                    'border-zinc-400 italic text-zinc-500' => $occupant->guest === null,
                                                    'border-transparent hover:border-zinc-400' => $occupant->guest !== null,
                                                ])>
                                                {{ $occupant->displayName() }}
                                            </button>
                                        @else
                                            <span @class(['text-sm', 'italic text-zinc-500' => $occupant->guest === null])>{{ $occupant->displayName() }}</span>
                                        @endif
                                        @if ($occupant->guest_id !== null && isset($this->occupantConflicts[$occupant->guest_id]))
                                            <flux:tooltip :content="$occupant->guest_id === $reservation->guest_id
                                                ? __('The booker is also staying there. If they booked for someone else, use Not staying to name who sleeps here.')
                                                : __('This person is also in a room on another reservation for these dates.')">
                                                <flux:badge size="sm" color="orange" icon="exclamation-triangle">{{ __('Also in :where', ['where' => $this->occupantConflicts[$occupant->guest_id]]) }}</flux:badge>
                                            </flux:tooltip>
                                        @endif
                                        @if ($occupant->guest !== null && ! $occupant->isChild() && blank($occupant->guest->id_number))
                                            <flux:badge size="sm" color="red" icon="identification">{{ __('ID missing') }}</flux:badge>
                                        @endif
                                        @if ($occupant->is_primary)
                                            <flux:badge size="sm" color="blue">{{ __('Primary') }}</flux:badge>
                                        @endif
                                        @if ($occupant->isChild())
                                            <flux:badge size="sm" color="amber">{{ __('Child') }}</flux:badge>
                                        @endif
                                    </div>

                                    @if ($this->canNameOccupants)
                                        <div class="flex items-center gap-1">
                                            @if ($occupant->guest === null)
                                                <flux:button size="xs" variant="ghost" icon="pencil-square"
                                                    wire:click="startNamingOccupant({{ $occupant->id }})">{{ __('Name') }}</flux:button>
                                            @elseif ($occupant->guest_id === $reservation->guest_id && $quickNamingOccupantId !== $occupant->id)
                                                <flux:button size="xs" variant="ghost" icon="user-minus"
                                                    title="{{ __('The booker is not sleeping here. Name who is.') }}"
                                                    wire:click="startQuickNaming({{ $occupant->id }})">{{ __('Not staying') }}</flux:button>
                                            @endif
                                            @if ($this->canChangeOccupancy)
                                                <flux:button size="xs" variant="ghost" icon="arrow-right-circle"
                                                    wire:click="startTransfer({{ $occupant->id }})">{{ __('Transfer') }}</flux:button>
                                                <flux:button size="xs" variant="ghost" icon="x-mark"
                                                    wire:click="removeOccupant({{ $occupant->id }})"
                                                    wire:confirm="{{ __('Remove :name from this room?', ['name' => $occupant->displayName()]) }}">{{ __('Remove') }}</flux:button>
                                            @endif
                                        </div>
                                    @endif
                                </li>

                                @if ($namingOccupantId === $occupant->id)
                                    <li class="py-3">
                                        @include('livewire.reservations.partials.occupant-form', ['naming' => true])
                                    </li>
                                @endif
                            @endforeach

                            @foreach ($formerOccupants as $occupant)
                                <li wire:key="former-occupant-{{ $occupant->id }}" class="flex items-center gap-2 py-2 text-zinc-400">
                                    <flux:icon name="arrow-left-start-on-rectangle" variant="mini" />
                                    <span class="text-sm line-through">{{ $occupant->displayName() }}</span>
                                    <span class="text-xs">{{ __('left :date', ['date' => $occupant->checked_out_at->format('M j, H:i')]) }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @if ($addingToRoomId === $rr->id)
                            <div class="mt-3">
                                @include('livewire.reservations.partials.occupant-form', ['naming' => false])
                            </div>
                        @elseif ($this->canChangeOccupancy)
                            <div class="mt-2">
                                @if ($roomIsFull)
                                    <flux:text class="text-xs">{{ __('Room is at full capacity.') }}</flux:text>
                                @else
                                    <flux:button size="sm" variant="subtle" icon="user-plus" wire:click="startAddingOccupant({{ $rr->id }})">
                                        {{ __('Add person') }}
                                    </flux:button>
                                @endif
                            </div>
                        @endif

                        @if ($rr->moves->isNotEmpty())
                            <div class="mt-3 space-y-1 border-t border-zinc-100 pt-2 dark:border-zinc-800">
                                @foreach ($rr->moves as $move)
                                    <flux:text class="text-xs">
                                        {{ __('Moved :from → :to from :date', [
                                            'from' => $move->fromRoom?->number ?? '—',
                                            'to' => $move->toRoom?->number ?? '—',
                                            'date' => $move->effective_date->format('M j'),
                                        ]) }}
                                        · {{ $move->rate_action === 'new_rate' ? __('new rate') : __('rate kept') }}@if ($move->reason) · {{ $move->reason }}@endif
                                    </flux:text>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <flux:separator />

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Adults') }}</flux:text>
                    <flux:heading size="sm">{{ $reservation->adults }}</flux:heading>
                </div>
                <div>
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Children') }}</flux:text>
                    <flux:heading size="sm">{{ $reservation->children }}</flux:heading>
                </div>
                <div>
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Source') }}</flux:text>
                    <flux:heading size="sm">{{ str_replace('_', ' ', ucfirst($reservation->source)) }}</flux:heading>
                </div>
                <div>
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Booker') }}</flux:text>
                    <flux:heading size="sm">{{ $reservation->agent?->name ?? __('Walk-in') }}</flux:heading>
                </div>
                @if ($reservation->stay)
                    <div>
                        <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Checked in') }}</flux:text>
                        <flux:heading size="sm">{{ $reservation->stay->checked_in_at?->format('M j, H:i') }}</flux:heading>
                    </div>
                @endif
            </div>

            @if ($reservation->notes)
                <flux:separator />
                <div>
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Special request') }}</flux:text>
                    <flux:text>{{ $reservation->notes }}</flux:text>
                </div>
            @endif
        </flux:card>

        <flux:card class="space-y-4">
            <flux:heading size="lg">{{ __('Actions') }}</flux:heading>

            @if ($reservation->status === 'confirmed')
                <flux:button variant="primary" icon="key" class="w-full" wire:click="checkIn">
                    {{ __('Check in') }}
                </flux:button>
                <flux:button variant="ghost" icon="calendar-days" class="w-full" wire:click="startChangingDates">
                    {{ __('Change dates') }}
                </flux:button>
                @if ($this->canMarkNoShow())
                    <flux:button variant="ghost" icon="user-minus" class="w-full"
                        wire:click="markNoShow" wire:confirm="{{ __('Mark this guest as a no-show? The room is released.') }}">
                        {{ __('Mark no-show') }}
                    </flux:button>
                @endif
                <flux:button variant="ghost" icon="x-mark" class="w-full"
                    wire:click="cancel" wire:confirm="{{ __('Cancel this reservation?') }}">
                    {{ __('Cancel reservation') }}
                </flux:button>
            @elseif ($reservation->status === 'checked_in')
                <flux:button variant="primary" icon="arrow-right-start-on-rectangle" class="w-full"
                    wire:click="checkOut">
                    {{ __('Check out') }}
                </flux:button>
                <flux:button variant="ghost" icon="calendar-days" class="w-full" wire:click="startChangingDates">
                    {{ __('Change departure') }}
                </flux:button>
            @endif

            <flux:modal name="change-dates" class="md:w-[480px]">
                <form wire:submit="changeDates" class="space-y-6">
                    <flux:heading size="lg">{{ $reservation->status === 'checked_in' ? __('Change departure') : __('Change dates') }}</flux:heading>
                    @if ($reservation->status === 'checked_in')
                        <flux:text>{{ __('Added nights are billed to the guest billing; nights dropped are credited.') }}</flux:text>
                    @endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input type="date" wire:model="newArrival" :label="__('Arrival')"
                            :disabled="$reservation->status === 'checked_in'" required />
                        <flux:input type="date" wire:model="newDeparture" :label="__('Departure')" required />
                    </div>
                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary">{{ __('Save dates') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            @if ($reservation->stay?->folio)
                <flux:separator />
                <flux:button variant="ghost" icon="document-text" class="w-full"
                    :href="route('folios.show', $reservation->stay->folio)" wire:navigate>
                    {{ __('Open guest billing') }}
                </flux:button>

                <div class="rounded-lg bg-zinc-50 px-4 py-3 text-sm dark:bg-zinc-800">
                    <div class="flex justify-between"><span>{{ __('Charges') }}</span><span class="font-mono">{{ $reservation->stay->folio->total_amount->format() }}</span></div>
                    <div class="flex justify-between"><span>{{ __('Paid') }}</span><span class="font-mono">{{ $reservation->stay->folio->paid_total->format() }}</span></div>
                    <flux:separator class="my-2" />
                    <div class="flex justify-between font-semibold">
                        <span>{{ __('Balance') }}</span>
                        <span class="font-mono">{{ $reservation->stay->folio->total_amount->subtract($reservation->stay->folio->paid_total)->format() }}</span>
                    </div>
                </div>
            @endif
        </flux:card>
    </div>

    <flux:modal name="move-room" class="md:w-[460px]">
        <form wire:submit="moveRoom" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Move room') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Everyone in the room moves for the remaining nights. Nights already spent stay on the old room.') }}</flux:text>
            </div>

            <flux:select wire:model="moveToRoomId" :label="__('New room')" :placeholder="__('Choose a free room…')">
                @foreach ($this->roomsFreeForRemainingNights as $freeRoom)
                    <flux:select.option :value="$freeRoom->id">
                        {{ $freeRoom->number }} — {{ $freeRoom->roomType?->name }} ({{ __('sleeps :max', ['max' => $freeRoom->roomType?->max_occupancy]) }})
                    </flux:select.option>
                @endforeach
            </flux:select>
            @if ($this->roomsFreeForRemainingNights->isEmpty())
                <flux:text class="text-sm">{{ __('No rooms are free for the remaining nights.') }}</flux:text>
            @endif

            <flux:radio.group wire:model="moveRateAction" :label="__('Rate')">
                <flux:radio value="keep_rate" :label="__('Keep the current rate')" :description="__('Hotel-caused moves, complimentary upgrades.')" />
                <flux:radio value="new_rate" :label="__('Charge the new room\'s rate')" :description="__('Guest-requested changes. The difference is posted to the guest billing.')" />
            </flux:radio.group>

            <flux:input wire:model="moveReason" :label="__('Reason')" :placeholder="__('e.g. AC broken, guest upgrade')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Move') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="transfer-occupant" class="md:w-[460px]">
        <form wire:submit="transferOccupant" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Transfer guest') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Moves only this person. Both rooms are re-priced from tonight.') }}</flux:text>
            </div>

            @php
                $transferSourceRoomId = $reservation->rooms
                    ->first(fn ($rr) => $rr->occupants->contains('id', $transferringOccupantId))?->id;
            @endphp

            <flux:select wire:model="transferTarget" :label="__('Move to')" :placeholder="__('Choose a room…')">
                @foreach ($reservation->rooms->where('id', '!=', $transferSourceRoomId) as $otherRoom)
                    <flux:select.option value="reservation-room:{{ $otherRoom->id }}">
                        {{ __('Room :number (on this reservation)', ['number' => $otherRoom->room?->number ?? '—']) }}
                    </flux:select.option>
                @endforeach
                @foreach ($this->roomsFreeForRemainingNights as $freeRoom)
                    <flux:select.option value="room:{{ $freeRoom->id }}">
                        {{ __('Room :number — :type (add to reservation)', ['number' => $freeRoom->number, 'type' => $freeRoom->roomType?->name]) }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Transfer') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <livewire:reservations.new-guest-form />
</section>
