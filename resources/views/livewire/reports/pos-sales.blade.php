@php
    $amount = fn (\App\Support\Money $money): string => number_format($money->toMajor(), 0);
    $money = fn (\App\Support\Money $money): string => number_format($money->toMajor(), 2);
    $totals = $this->totals;
    $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
    $td = 'px-4 py-3 whitespace-nowrap';
    $tabs = ['orders' => __('By Order'), 'items' => __('By Item'), 'cleared' => __('Cleared Orders')];
@endphp

<x-reports.shell
    :heading="$this->heading()"
    :icon="$this->icon()"
    :subtitle="__('Billed sales from :from to :to', ['from' => $this->periodStart()->format('d M Y'), 'to' => $this->periodEnd()->format('d M Y')])"
    :tiles="[
        [__('Orders'), $totals['orders'], 'text-zinc-900 dark:text-zinc-100'],
        [__('Items Sold'), $totals['quantity'], 'text-zinc-900 dark:text-zinc-100'],
        [__('Tax'), $amount($totals['tax']), 'text-zinc-900 dark:text-zinc-100'],
        [__('Sales'), $amount($totals['total']), 'text-green-600 dark:text-green-400'],
    ]"
    :footnote="__('Currency:') . ' ' . $this->hotelCurrency()"
>
    <x-slot:nav>
        @foreach ($tabs as $key => $label)
            @if (! $loop->first)
                <span class="text-zinc-400 dark:text-zinc-600">|</span>
            @endif
            <button type="button" wire:click="showView('{{ $key }}')"
                @class([
                    'hover:underline',
                    'font-semibold text-zinc-900 dark:text-zinc-100' => $view === $key,
                    'text-sky-600 dark:text-sky-400' => $view !== $key,
                ])>{{ $label }}</button>
        @endforeach
    </x-slot:nav>

    <x-slot:filters>
        <div class="w-full sm:w-56">
            <flux:select wire:model="outlet" :label="__('Outlet')">
                <flux:select.option value="">{{ __('All outlets') }}</flux:select.option>
                @foreach ($this->outlets as $outletOption)
                    <flux:select.option :value="$outletOption->id">{{ $outletOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </x-slot:filters>

    <table class="w-full text-sm text-zinc-700 dark:text-zinc-300">
        @if ($view === 'cleared')
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60">
                <tr>
                    <th class="{{ $th }} text-left">{{ __('Cleared At') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Cleared By') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Outlet') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Table') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Items') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->clearedOrders as $clearedOrder)
                    <tr wire:key="cleared-{{ $clearedOrder->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                        <td class="{{ $td }}" title="{{ $clearedOrder->reference }}">{{ $clearedOrder->cleared_at?->format('d-m-Y H:i') }}</td>
                        <td class="{{ $td }} font-medium text-zinc-900 dark:text-zinc-100">{{ $clearedOrder->clearedBy?->name ?? '—' }}</td>
                        <td class="{{ $td }}">{{ $clearedOrder->outlet->name }}</td>
                        <td class="{{ $td }}">{{ $clearedOrder->table_no ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $clearedOrder->items->map(fn ($line) => $line->quantity . ' × ' . $line->name_snapshot)->implode(', ') }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ $money($clearedOrder->total_amount) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-12 text-center text-zinc-500">{{ __('No orders were cleared in this period.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        @elseif ($view === 'items')
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60">
                <tr>
                    <th class="{{ $th }} text-left">{{ __('Item') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Outlet') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Quantity') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->itemRows as $row)
                    <tr wire:key="item-{{ $loop->index }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                        <td class="{{ $td }} font-medium text-zinc-900 dark:text-zinc-100">{{ $row['name'] }}</td>
                        <td class="{{ $td }}">{{ $row['outlet'] }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ $row['quantity'] }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ $amount($row['total']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-zinc-500">{{ __('No sales in this period.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($this->itemRows !== [])
                <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-100">
                    <tr>
                        <td colspan="2" class="{{ $td }}">{{ __('Total') }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ $totals['quantity'] }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ $amount($totals['total']) }}</td>
                    </tr>
                </tfoot>
            @endif
        @else
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60">
                <tr>
                    <th class="{{ $th }} text-left">{{ __('Date') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Customer') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Currency') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Total') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Discount') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Grand Total') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Paid By') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Amount Paid') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->orderRows as $row)
                    <tr wire:key="order-{{ $row['order']->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                        <td class="{{ $td }}">
                            <a href="{{ route('pos.transactions.show', $row['order']) }}" wire:navigate class="text-sky-600 hover:underline dark:text-sky-400"
                                title="{{ $row['order']->reference }}">{{ $row['order']->billed_at?->format('d-m-Y H:i') }}</a>
                        </td>
                        <td class="{{ $td }} font-medium text-zinc-900 dark:text-zinc-100">{{ $row['customer'] ?? __('Walk-in') }}</td>
                        <td class="{{ $td }}">{{ $row['currency'] }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ $money($row['total']) }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ $money($row['discount']) }}</td>
                        <td class="{{ $td }} text-right font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ $money($row['grand_total']) }}</td>
                        <td class="{{ $td }}">{{ $row['paid_by'] !== '' ? $row['paid_by'] : '—' }}</td>
                        <td class="{{ $td }} text-right tabular-nums text-green-600 dark:text-green-400">{{ $money($row['paid']) }}</td>
                        <td @class([$td, 'text-right font-medium tabular-nums', 'text-red-600 dark:text-red-400' => $row['balance']->isPositive()])>{{ $money($row['balance']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-zinc-500">{{ __('No sales in this period.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-100">
                <tr>
                    <td colspan="3" class="{{ $td }} text-base">{{ __('Total') }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $money($totals['total']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $money(\App\Support\Money::zero()) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $money($totals['total']) }}</td>
                    <td class="{{ $td }}"></td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $money($totals['paid']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $money($totals['balance']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</x-reports.shell>
