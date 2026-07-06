<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.information-circle class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">
                {{ __('Transaction') }} <span class="font-mono">{{ $posOrder->reference }}</span>
            </flux:heading>

            @php
                $statusColor = match ($posOrder->status) {
                    'open' => 'bg-blue-600',
                    'sent' => 'bg-yellow-500',
                    'billed' => 'bg-green-600',
                    'voided' => 'bg-zinc-500',
                    default => 'bg-zinc-500',
                };
            @endphp
            <span class="inline-flex items-center rounded-md px-3 py-1 text-xs font-semibold uppercase tracking-wide text-white {{ $statusColor }}">
                {{ $posOrder->status }}
            </span>

            <a href="{{ route('pos.terminal', $posOrder->outlet) }}" wire:navigate
                class="ml-auto inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to terminal') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('pos.lobby') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('POS') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('pos.terminal', $posOrder->outlet) }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ $posOrder->outlet->name }}</a>
            @if ($posOrder->folio)
                <span class="text-zinc-400 dark:text-zinc-600">|</span>
                <a href="{{ route('folios.show', $posOrder->folio) }}" wire:navigate
                    class="text-sky-600 hover:underline dark:text-sky-400">
                    {{ __('Folio') }} {{ $posOrder->folio->reference }}
                </a>
            @endif
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <flux:text class="text-xs uppercase text-zinc-500">{{ __('Outlet') }}</flux:text>
                <div class="font-medium">{{ $posOrder->outlet->name }}</div>
            </div>
            <div>
                <flux:text class="text-xs uppercase text-zinc-500">{{ __('Table') }}</flux:text>
                <div class="font-medium">{{ $posOrder->table_no ?? '—' }}</div>
            </div>
            <div>
                <flux:text class="text-xs uppercase text-zinc-500">{{ __('Opened') }}</flux:text>
                <div class="font-medium">{{ $posOrder->created_at?->format('M j, Y H:i') }}</div>
            </div>
            <div>
                <flux:text class="text-xs uppercase text-zinc-500">{{ __('Billed') }}</flux:text>
                <div class="font-medium">
                    {{ $posOrder->billed_at?->format('M j, Y H:i') ?? '—' }}
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">{{ __('Items') }}</flux:heading>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Item') }}</flux:table.column>
                <flux:table.column>{{ __('Qty') }}</flux:table.column>
                <flux:table.column>{{ __('Unit') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Line total') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($posOrder->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell variant="strong">{{ $item->name_snapshot }}</flux:table.cell>
                        <flux:table.cell>{{ $item->quantity }}</flux:table.cell>
                        <flux:table.cell class="font-mono">{{ $item->unit_price->format() }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ $item->line_total->format() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">
                            {{ __('No items on this transaction.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <flux:separator class="my-4" />

        <div class="ml-auto max-w-sm space-y-2">
            <div class="flex justify-between text-sm">
                <span>{{ __('Subtotal') }}</span>
                <span class="font-mono">{{ $posOrder->subtotal->format() }}</span>
            </div>
            <div class="flex justify-between text-sm">
                <span>{{ __('Tax') }}</span>
                <span class="font-mono">{{ $posOrder->tax_total->format() }}</span>
            </div>
            <flux:separator />
            <div class="flex justify-between">
                <flux:heading size="sm">{{ __('Total') }}</flux:heading>
                <span class="font-mono font-semibold">{{ $posOrder->total_amount->format() }}</span>
            </div>
        </div>
    </div>
</section>
