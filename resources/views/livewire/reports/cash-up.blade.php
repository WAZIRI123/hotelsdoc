<section class="w-full max-w-4xl">
    <div class="flex items-center justify-between gap-4 pb-6">
        <div>
            <flux:heading size="xl">{{ __('Daily cash-up') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Confirmed payments collected on the selected day.') }}</flux:text>
        </div>

        <flux:input type="date" wire:model.live="date" />
    </div>

    <flux:card class="mb-6">
        <flux:heading size="lg">{{ __('Totals by method') }}</flux:heading>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Method') }}</flux:table.column>
                <flux:table.column>{{ __('Payments') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Total') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->totalsByMethod as $code => $group)
                    <flux:table.row :key="$code">
                        <flux:table.cell variant="strong">{{ $group['label'] }}</flux:table.cell>
                        <flux:table.cell>{{ $group['count'] }}</flux:table.cell>
                        <flux:table.cell class="text-right font-mono">{{ $group['total']->format() }}</flux:table.cell>
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
                <flux:table.column class="text-right">{{ __('Amount') }}</flux:table.column>
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
                        <flux:table.cell class="text-right font-mono">{{ $payment->amount->format() }}</flux:table.cell>
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
</section>
