<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.user-group class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('All Guests') }}</flux:heading>

            <a href="{{ route('guests.register') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Register Guest') }}
            </a>

            <a href="{{ route('guests.index') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Guest Book') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('guests.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Guest Book') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('guests.register') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Register Guest') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('reservations.create') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('New Reservation') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <div class="flex flex-wrap items-center justify-between gap-3 pb-4">
            <flux:text>{{ __('Search and browse every guest in your hotel.') }}</flux:text>
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="{{ __('Name, caller, phone, email…') }}" />
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Gender') }}</flux:table.column>
                <flux:table.column>{{ __('Caller name') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Nationality') }}</flux:table.column>
                <flux:table.column>{{ __('Reservations') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->guests as $guest)
                    <flux:table.row :key="$guest->id">
                        <flux:table.cell variant="strong">{{ $guest->fullName() }}</flux:table.cell>
                        <flux:table.cell>{{ $guest->gender ? __(ucfirst($guest->gender)) : '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $guest->caller_name ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $guest->phone ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $guest->email ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $guest->nationality ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $guest->reservations_count }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('guests.edit', $guest)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500">
                            {{ __('No guests found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $this->guests->links() }}
        </div>
    </div>
</section>
