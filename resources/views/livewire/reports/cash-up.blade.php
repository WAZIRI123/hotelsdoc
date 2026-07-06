<section class="w-full max-w-4xl">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.banknotes class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Daily cash-up') }}</flux:heading>

            <flux:spacer />

            <flux:input type="date" wire:model.live="date" />
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('pos.lobby') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('POS terminal') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('outlets.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Outlets') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('guests.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Guest Book') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

    <flux:card class="mb-6">
        <flux:heading size="lg">{{ __('Totals by method') }}</flux:heading>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Method') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Payments') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Total') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->totalsByMethod as $code => $group)
                    <flux:table.row :key="$code">
                        <flux:table.cell variant="strong">{{ $group['label'] }}</flux:table.cell>
                        <flux:table.cell align="center">{{ $group['count'] }}</flux:table.cell>
                        <flux:table.cell align="center" class="font-mono">{{ $group['total']->format() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="text-center text-zinc-500">
                            {{ __('No payments on this day.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <flux:separator class="my-4" />

        <div class="flex justify-between">
            <flux:heading size="sm">{{ __('Grand total') }}</flux:heading>
            <span class="font-mono font-semibold">{{ $this->grandTotal->format() }}</span>
        </div>
    </flux:card>

    <flux:card>
        <flux:heading size="lg">{{ __('All payments') }}</flux:heading>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Time') }}</flux:table.column>
                <flux:table.column>{{ __('Folio') }}</flux:table.column>
                <flux:table.column>{{ __('Method') }}</flux:table.column>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Amount') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->payments as $payment)
                    <flux:table.row :key="$payment->id">
                        <flux:table.cell>{{ $payment->confirmed_at?->format('H:i') }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-sm">
                            <flux:link :href="route('folios.show', $payment->folio)" wire:navigate>
                                {{ $payment->folio?->reference }}
                            </flux:link>
                        </flux:table.cell>
                        <flux:table.cell>{{ ucwords(str_replace('_', ' ', $payment->method_code)) }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $payment->reference ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="center" class="font-mono">{{ $payment->amount->format() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-500">
                            {{ __('No payments recorded.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>
    </div>
</section>
