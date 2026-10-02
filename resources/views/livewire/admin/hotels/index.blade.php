<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.building-office class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Hotels') }}</flux:heading>

            <a href="{{ route('admin.hotels.create') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('New Hotel') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('admin.hotels.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('All Hotels') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('admin.hotels.create') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Set up a new hotel') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <div class="mb-4 max-w-sm">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name, slug or email…')" />
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Hotel') }}</flux:table.column>
                <flux:table.column>{{ __('Contact') }}</flux:table.column>
                <flux:table.column>{{ __('Currency') }}</flux:table.column>
                <flux:table.column>{{ __('Users') }}</flux:table.column>
                <flux:table.column>{{ __('Rooms') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Created') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->hotels as $hotel)
                    <flux:table.row :key="$hotel->id">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $hotel->name }}</div>
                            <div class="text-xs text-zinc-500">{{ $hotel->slug }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div>{{ $hotel->email ?? '—' }}</div>
                            <div class="text-xs text-zinc-500">{{ $hotel->phone }}</div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $hotel->currency }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($hotel->users_count === 0)
                                <flux:badge color="amber" size="sm">{{ __('No users') }}</flux:badge>
                            @else
                                {{ $hotel->users_count }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($hotel->rooms_count === 0)
                                <flux:badge color="amber" size="sm">{{ __('Not set up') }}</flux:badge>
                            @else
                                {{ $hotel->rooms_count }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($hotel->isSuspended())
                                <flux:badge color="red" size="sm">{{ __('Suspended') }}</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $hotel->created_at?->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            <div class="flex items-center justify-end gap-1">
                                <form method="POST" action="{{ route('hotels.switch', $hotel) }}">
                                    @csrf
                                    <flux:button type="submit" size="sm" variant="ghost" icon="arrow-right-end-on-rectangle">
                                        {{ __('Enter') }}
                                    </flux:button>
                                </form>
                                <flux:button size="sm" variant="ghost" icon="cog-6-tooth" :href="route('admin.hotels.show', $hotel)" wire:navigate>
                                    {{ __('Manage') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500">
                            {{ $search === '' ? __('No hotels yet.') : __('No hotels match your search.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
