<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.briefcase class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Agents') }}</flux:heading>

            <a href="{{ route('agents.register') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Register Agent') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('agents.register') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Register Agent') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('reservations.create') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('New Reservation') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <div class="flex flex-wrap items-center justify-between gap-3 pb-4">
            <flux:text>{{ __('Travel agents and tour operators that book on behalf of guests.') }}</flux:text>
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                placeholder="{{ __('Name, phone, email, TIN, VRN…') }}" />
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Qualification') }}</flux:table.column>
                <flux:table.column>{{ __('Phone') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('TIN') }}</flux:table.column>
                <flux:table.column>{{ __('VRN') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->agents as $agent)
                    <flux:table.row :key="$agent->id">
                        <flux:table.cell variant="strong">{{ $agent->name }}</flux:table.cell>
                        <flux:table.cell>{{ $agent->qualification?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $agent->phone ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $agent->email ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $agent->tin ?: '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $agent->vrn ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($agent->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" :href="route('agents.edit', $agent)" wire:navigate>
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger"
                                        wire:click="delete({{ $agent->id }})"
                                        wire:confirm="{{ __('Delete this agent?') }}">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500">
                            {{ __('No agents yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">
            {{ $this->agents->links() }}
        </div>
    </div>
</section>
