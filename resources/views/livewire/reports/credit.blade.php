@php
    $amount = fn (\App\Support\Money $money): string => number_format($money->toMajor(), 0);
    $totals = $this->totals;
    $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
    $td = 'px-4 py-3 whitespace-nowrap';
    $summary = [
        [__('Accommodation Total Credit'), $amount($totals['accommodation'])],
        [__('Restaurant Total Credits'), $amount($totals['restaurant'])],
        [__('Bar Total Credits'), $amount($totals['bar'])],
        [__('Total Credits'), number_format($totals['total']->toMajor(), 2)],
    ];
@endphp

<x-reports.shell
    :heading="__('Credit Report')"
    icon="document-text"
    :subtitle="__('Unpaid balances from :from to :to', ['from' => $this->periodStart()->format('d M Y'), 'to' => $this->periodEnd()->format('d M Y')]) . ' · ' . $currency"
    :tiles="[
        [__('Accommodation'), $amount($totals['accommodation']), 'text-zinc-900 dark:text-zinc-100'],
        [__('Restaurant'), $amount($totals['restaurant']), 'text-zinc-900 dark:text-zinc-100'],
        [__('Bar'), $amount($totals['bar']), 'text-zinc-900 dark:text-zinc-100'],
        [__('Total Credits'), $amount($totals['total']), 'text-red-600 dark:text-red-400'],
    ]"
    :footnote="__('Currency:') . ' ' . $currency"
>
    <x-slot:filters>
        <div class="w-full sm:w-40">
            <flux:select wire:model="currency" :label="__('Currency')">
                @foreach ($this->currencies as $code)
                    <flux:select.option :value="$code">{{ $code }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </x-slot:filters>

    <table class="w-full text-sm text-zinc-700 dark:text-zinc-300">
        <thead class="border-b border-zinc-200 dark:border-zinc-700">
            <tr>
                <th class="{{ $th }} text-left">{{ __('Date') }}</th>
                <th class="{{ $th }} text-left">{{ __('Customer') }}</th>
                <th class="{{ $th }} text-right">{{ __('Accommodation') }}</th>
                <th class="{{ $th }} text-right">{{ __('Restaurant') }}</th>
                <th class="{{ $th }} text-right">{{ __('Bar') }}</th>
                <th class="{{ $th }} text-right">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($this->rows as $row)
                <tr wire:key="credit-{{ $row['folio']->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                    <td class="{{ $td }}">{{ $row['opened']->format('d-m-Y') }}</td>
                    <td class="{{ $td }} font-medium text-zinc-900 dark:text-zinc-100">
                        <a href="{{ route('folios.show', $row['folio']) }}" wire:navigate class="hover:underline">{{ $row['customer'] ?? __('Walk-in') }}</a>
                    </td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($row['accommodation']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($row['restaurant']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($row['bar']) }}</td>
                    <td class="{{ $td }} text-right font-medium tabular-nums text-red-600 dark:text-red-400">{{ $amount($row['total']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-12 text-center text-zinc-500">{{ __('No outstanding balances in this period.') }}</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot class="border-t-2 border-zinc-200 text-zinc-900 dark:border-zinc-700 dark:text-zinc-100">
            @foreach ($summary as [$label, $value])
                <tr wire:key="credit-summary-{{ $loop->index }}" class="border-b border-zinc-200 dark:border-zinc-700">
                    <td colspan="2" class="{{ $td }} text-base font-semibold uppercase">{{ $label }}</td>
                    <td class="{{ $td }} text-right font-semibold tabular-nums">{{ $value }}</td>
                    <td colspan="3"></td>
                </tr>
            @endforeach
        </tfoot>
    </table>
</x-reports.shell>
