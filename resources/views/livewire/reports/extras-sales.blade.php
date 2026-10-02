@php
    $amount = fn (\App\Support\Money $money): string => number_format($money->toMajor(), 0);
    $money = fn (\App\Support\Money $money): string => number_format($money->toMajor(), 2);
    $totals = $this->totals;
    $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
    $td = 'px-4 py-3 whitespace-nowrap';
    $tabs = ['orders' => __('By Order'), 'items' => __('By Extra')];
@endphp

<x-reports.shell
    :heading="__('Extras Sales Report')"
    icon="cube"
    :subtitle="__('Extras sold from :from to :to', ['from' => $this->periodStart()->format('d M Y'), 'to' => $this->periodEnd()->format('d M Y')]) . ' · ' . $this->hotelCurrency()"
    :tiles="[
        [__('Orders'), $totals['orders'], 'text-zinc-900 dark:text-zinc-100'],
        [__('Walk-in Orders'), $totals['walk_in'], 'text-zinc-900 dark:text-zinc-100'],
        [__('Items Sold'), $totals['quantity'], 'text-zinc-900 dark:text-zinc-100'],
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

    <table class="w-full text-sm text-zinc-700 dark:text-zinc-300">
        @if ($view === 'items')
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60">
                <tr>
                    <th class="{{ $th }} text-left">{{ __('Extra') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Quantity') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->itemRows as $row)
                    <tr wire:key="extra-{{ $loop->index }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                        <td class="{{ $td }} font-medium text-zinc-900 dark:text-zinc-100">{{ $row['name'] }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ $row['quantity'] }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ $amount($row['total']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-12 text-center text-zinc-500">{{ __('No extras sold in this period.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($this->itemRows !== [])
                <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-100">
                    <tr>
                        <td class="{{ $td }}">{{ __('Total') }}</td>
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
                    <tr wire:key="extra-order-{{ $row['order']->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40"
                        title="{{ $row['order']->reference }}: {{ $row['order']->items->map(fn ($item) => $item->quantity . ' × ' . $item->name_snapshot)->implode(', ') }}">
                        <td class="{{ $td }}">{{ $row['order']->order_date->format('d-m-Y') }}</td>
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
                        <td colspan="9" class="px-4 py-12 text-center text-zinc-500">{{ __('No extras sold in this period.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-100">
                <tr>
                    <td colspan="3" class="{{ $td }} text-base">{{ __('Total') }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $money($totals['total']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $money(\App\Support\Money::zero($this->hotelCurrency())) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $money($totals['total']) }}</td>
                    <td class="{{ $td }}"></td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $money($totals['paid']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $money($totals['balance']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</x-reports.shell>
