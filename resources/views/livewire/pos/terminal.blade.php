<section class="w-full">
    <div class="flex items-center justify-between pb-4">
        <div>
            <flux:text class="text-sm">
                <flux:link :href="route('pos.lobby')" wire:navigate>{{ __('POS') }}</flux:link>
                / {{ $outlet->name }}
            </flux:text>
            <flux:heading size="xl">{{ $outlet->name }}</flux:heading>
        </div>
        <flux:input wire:model.blur="tableNo" placeholder="{{ __('Table #') }}" class="w-32" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Menu (2 cols on desktop) --}}
        <div class="lg:col-span-2 space-y-6">
            @forelse ($this->menu as $category)
                @if ($category->items->isNotEmpty())
                    <div>
                        <flux:heading size="lg" class="mb-3">{{ $category->name }}</flux:heading>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            @foreach ($category->items as $item)
                                <button type="button" wire:click="addItem({{ $item->id }})"
                                    class="rounded-lg border border-zinc-200 bg-white p-3 text-left transition hover:border-blue-500 hover:shadow dark:border-zinc-700 dark:bg-zinc-900">
                                    <div class="font-medium">{{ $item->name }}</div>
                                    <div class="mt-1 font-mono text-sm text-zinc-500">{{ $item->price->format() }}</div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            @empty
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>{{ __('No menu items yet') }}</flux:callout.heading>
                    <flux:callout.text>
                        <flux:link :href="route('menu.index', $outlet)" wire:navigate>{{ __('Set up the menu first') }}</flux:link>.
                    </flux:callout.text>
                </flux:callout>
            @endforelse
        </div>

        {{-- Order panel --}}
        <flux:card class="lg:sticky lg:top-4 self-start">
            <div class="flex items-center justify-between">
                <flux:heading size="lg">{{ __('Current order') }}</flux:heading>
                @if ($this->order)
                    <flux:text class="font-mono text-xs">{{ $this->order->reference }}</flux:text>
                @endif
            </div>

            <div class="mt-4 space-y-2 max-h-[420px] overflow-y-auto">
                @forelse ($this->order?->items ?? [] as $item)
                    <div class="flex items-center justify-between gap-2 border-b border-zinc-100 pb-2 dark:border-zinc-800">
                        <div class="flex-1">
                            <div class="font-medium">{{ $item->name_snapshot }}</div>
                            <div class="text-xs font-mono text-zinc-500">{{ $item->unit_price->format() }} ea.</div>
                        </div>
                        <div class="flex items-center gap-1">
                            <flux:button variant="ghost" size="sm" icon="minus" wire:click="changeQty({{ $item->id }}, -1)" />
                            <span class="w-8 text-center font-mono">{{ $item->quantity }}</span>
                            <flux:button variant="ghost" size="sm" icon="plus" wire:click="changeQty({{ $item->id }}, 1)" />
                        </div>
                        <div class="w-24 text-right font-mono">{{ $item->line_total->format() }}</div>
                    </div>
                @empty
                    <flux:text class="text-sm text-zinc-500">{{ __('No items yet. Tap a menu item to add it.') }}</flux:text>
                @endforelse
            </div>

            @if ($this->order)
                <flux:separator class="my-4" />
                <div class="flex justify-between text-lg">
                    <flux:heading size="sm">{{ __('Total') }}</flux:heading>
                    <span class="font-mono font-semibold">{{ $this->order->total_amount->format() }}</span>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <flux:button variant="ghost" icon="x-mark"
                        wire:click="voidOrder" wire:confirm="{{ __('Void this order?') }}">
                        {{ __('Void') }}
                    </flux:button>
                    <flux:button variant="primary" icon="banknotes" wire:click="openSettle"
                        :disabled="$this->order->items->isEmpty()">
                        {{ __('Settle') }}
                    </flux:button>
                </div>
            @endif
        </flux:card>
    </div>

    <flux:card class="mt-6">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="lg">{{ __('Transactions') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">
                    {{ __('Recent orders for this outlet.') }}
                </flux:text>
            </div>
        </div>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Reference') }}</flux:table.column>
                <flux:table.column>{{ __('Opened') }}</flux:table.column>
                <flux:table.column>{{ __('Table') }}</flux:table.column>
                <flux:table.column>{{ __('Items') }}</flux:table.column>
                <flux:table.column>{{ __('Billed to') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('Total') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->transactions as $transaction)
                    <flux:table.row :key="$transaction->id">
                        <flux:table.cell class="font-mono text-sm">{{ $transaction->reference }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->created_at?->format('M j, H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->table_no ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $transaction->items_count }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $transaction->folio?->guest?->fullName() ?? ($transaction->folio ? __('Walk-in') : '—') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @php
                                $color = match ($transaction->status) {
                                    'open' => 'blue',
                                    'sent' => 'yellow',
                                    'billed' => 'green',
                                    'voided' => 'zinc',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:badge :color="$color" size="sm">
                                {{ ucfirst($transaction->status) }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="text-right font-mono">
                            {{ $transaction->total_amount->format() }}
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:button variant="ghost" size="sm" icon="arrow-right"
                                :href="route('pos.transactions.show', $transaction)" wire:navigate />
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-zinc-500">
                            {{ __('No transactions yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="pos-settle" class="md:w-[520px]">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Settle order') }}</flux:heading>

            @if ($this->order)
                <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                    <div class="flex justify-between">
                        <span>{{ __('Total due') }}</span>
                        <span class="font-mono font-semibold">{{ $this->order->total_amount->format() }}</span>
                    </div>
                </div>
            @endif

            <flux:radio.group wire:model.live="settleMode" variant="segmented">
                <flux:radio value="room" :disabled="$this->inHouseStays->isEmpty()"
                    label="{{ __('Bill to room') }}" />
                <flux:radio value="walk_in" label="{{ __('Walk-in payment') }}" />
            </flux:radio.group>

            @if ($settleMode === 'room')
                <div class="space-y-3">
                    <flux:text class="text-sm text-zinc-500">
                        {{ __('Post this order onto an in-house guest\'s folio. They settle it at check-out.') }}
                    </flux:text>

                    @if ($this->inHouseStays->isEmpty())
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:callout.text>{{ __('No guests are currently checked in.') }}</flux:callout.text>
                        </flux:callout>
                    @else
                        <flux:select wire:model.live="billToStayId">
                            <flux:select.option value="">{{ __('Choose a guest…') }}</flux:select.option>
                            @foreach ($this->inHouseStays as $stay)
                                <flux:select.option :value="$stay->id">
                                    {{ $stay->guest?->fullName() }}
                                    — {{ __('Room') }} {{ $stay->reservation?->rooms->first()?->room?->number ?? '—' }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:button variant="primary" icon="home-modern" wire:click="billToRoom"
                            :disabled="$billToStayId === null">
                            {{ __('Post to guest folio') }}
                        </flux:button>
                    @endif
                </div>
            @else
                <div class="space-y-3">
                    <flux:text class="text-sm text-zinc-500">
                        {{ __('For walk-in customers paying at the counter. Creates a standalone folio and issues a receipt.') }}
                    </flux:text>

                    <flux:select wire:model.live="settleMethod">
                        <flux:select.option value="">{{ __('Choose method…') }}</flux:select.option>
                        @foreach ($this->paymentMethods as $method)
                            <flux:select.option :value="$method->code">{{ $method->label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @if ($settleMethod !== '' && $settleMethod !== 'cash')
                        <flux:input wire:model="settleReference" placeholder="{{ __('Reference / txn ID') }}" />
                    @endif

                    <flux:button variant="primary" icon="check" wire:click="settleDirect"
                        :disabled="$settleMethod === ''">
                        {{ __('Take payment') }}
                    </flux:button>
                </div>
            @endif

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
