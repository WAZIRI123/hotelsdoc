<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.list-bullet class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Extra Orders') }}</flux:heading>

            <a href="{{ route('extras.create') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Add Extras') }}
            </a>

            @can('manage-hotel')
                <a href="{{ route('extras.services') }}" wire:navigate
                    class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                    {{ __('Extra services') }}
                </a>
            @endcan
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="#" x-on:click.prevent="$wire.set('date', '')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('All') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#" x-on:click.prevent="$wire.set('date', '{{ now()->toDateString() }}')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Today') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="#" x-on:click.prevent="$wire.set('date', '{{ now()->subDay()->toDateString() }}')"
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Yesterday') }}</a>
            <span class="ms-auto">
                <flux:input type="date" size="sm" wire:model.live="date" />
            </span>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <flux:table :paginate="$this->orders">
            <flux:table.columns>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Customer') }}</flux:table.column>
                <flux:table.column>{{ __('Items') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                <flux:table.column>{{ __('Recorded by') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->orders as $order)
                    <flux:table.row :key="$order->id">
                        <flux:table.cell class="font-mono text-sm">{{ $order->reference }}</flux:table.cell>
                        <flux:table.cell>{{ $order->order_date->format('d M Y') }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($order->isWalkIn())
                                <flux:badge color="zinc" size="sm">{{ __('Walk-in') }}</flux:badge>
                            @else
                                {{ $order->stay?->guest?->fullName() ?? '—' }}
                                @if ($order->folio_id)
                                    <flux:link :href="route('folios.show', $order->folio_id)" wire:navigate class="ms-1 text-xs">{{ __('Folio') }}</flux:link>
                                @endif
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @foreach ($order->items as $item)
                                <div>{{ $item->quantity }} × {{ $item->name_snapshot }}</div>
                            @endforeach
                        </flux:table.cell>
                        <flux:table.cell align="end" variant="strong">{{ $order->total()->format() }}</flux:table.cell>
                        <flux:table.cell>{{ $order->creator?->name ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">
                            {{ __('No extra orders yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
