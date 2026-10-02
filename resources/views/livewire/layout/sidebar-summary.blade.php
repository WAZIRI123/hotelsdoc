<div class="mt-4 space-y-3 border-t border-zinc-200 pt-3 text-sm dark:border-zinc-700" data-test="sidebar-summary">
    <div>
        <div class="flex items-center gap-1.5 font-semibold text-zinc-800 dark:text-zinc-100">
            <flux:icon.arrow-down variant="micro" />
            {{ __('Rooms Status') }}
        </div>
        <div class="mt-1 space-y-0.5 text-blue-600 dark:text-blue-400">
            <div>{{ __('Total') }}: {{ $this->roomsStatus['total'] }} <span class="text-zinc-400">|</span> {{ __('Occupied') }}: {{ $this->roomsStatus['occupied'] }}</div>
            <div>{{ __('Today Bookings') }}: {{ $this->roomsStatus['todayBookings'] }} <span class="text-zinc-400">|</span> {{ __('Today Check-outs') }}: {{ $this->roomsStatus['todayCheckOuts'] }}</div>
        </div>
    </div>

    <div>
        <div class="flex items-center gap-1.5 font-semibold text-zinc-800 dark:text-zinc-100">
            <flux:icon.user variant="micro" />
            {{ __('Guests') }}
        </div>
        <div class="mt-1 text-blue-600 dark:text-blue-400">
            {{ __('Total today') }}: {{ $this->guests['total'] }} <span class="text-zinc-400">|</span>
            {{ __('Males') }}: {{ $this->guests['males'] }} <span class="text-zinc-400">|</span>
            {{ __('Females') }}: {{ $this->guests['females'] }}
        </div>
    </div>

    <div>
        <div class="flex items-center gap-1.5 font-semibold text-zinc-800 dark:text-zinc-100">
            <flux:icon.home-modern variant="micro" />
            {{ __('Available') }}
        </div>
        <div class="mt-1 text-blue-600 dark:text-blue-400">
            {{ __('Total') }}: {{ $this->available['total'] }}
            @foreach ($this->available['byType'] as $typeName => $count)
                <span class="text-zinc-400">|</span> {{ $typeName }}: {{ $count }}
            @endforeach
        </div>
    </div>
</div>
