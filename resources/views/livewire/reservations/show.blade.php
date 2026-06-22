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
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <div>
                            <flux:heading size="sm">
                                {{ __('Room') }} {{ $rr->room?->number ?? '—' }}
                                <flux:text class="inline">— {{ $rr->roomType?->name }}</flux:text>
                            </flux:heading>
                            <flux:text class="text-sm">{{ $rr->nightly_rate->format() }} / {{ __('night') }}</flux:text>
                        </div>
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
                    <flux:text class="text-xs uppercase tracking-wide text-zinc-500">{{ __('Notes') }}</flux:text>
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
                <flux:button variant="ghost" icon="x-mark" class="w-full"
                    wire:click="cancel" wire:confirm="{{ __('Cancel this reservation?') }}">
                    {{ __('Cancel reservation') }}
                </flux:button>
            @elseif ($reservation->status === 'checked_in')
                <flux:button variant="primary" icon="arrow-right-start-on-rectangle" class="w-full"
                    wire:click="checkOut">
                    {{ __('Check out') }}
                </flux:button>
            @endif

            @if ($reservation->stay?->folio)
                <flux:separator />
                <flux:button variant="ghost" icon="document-text" class="w-full"
                    :href="route('folios.show', $reservation->stay->folio)" wire:navigate>
                    {{ __('Open folio') }}
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
</section>
