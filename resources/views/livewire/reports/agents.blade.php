@php
    $amount = fn (\App\Support\Money $money): string => number_format($money->toMajor(), 0);
    $totals = $this->totals;
    $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
    $td = 'px-4 py-3 whitespace-nowrap';
@endphp

<x-reports.shell
    :heading="__('Agents Report')"
    icon="briefcase"
    :subtitle="__('Agent bookings arriving from :from to :to', ['from' => $this->periodStart()->format('d M Y'), 'to' => $this->periodEnd()->format('d M Y')]) . ' · ' . $this->hotelCurrency()"
    :tiles="[
        [__('Bookings'), number_format($totals['bookings']), 'text-zinc-900 dark:text-zinc-100'],
        [__('Room Nights'), number_format($totals['room_nights']), 'text-zinc-900 dark:text-zinc-100'],
        [__('Room Revenue'), $amount($totals['room_revenue']), 'text-green-600 dark:text-green-400'],
        [__('Outstanding'), $amount($totals['balance']), 'text-red-600 dark:text-red-400'],
    ]"
    :footnote="__('Counts reservations by arrival date. Cancelled and no-show bookings are listed but excluded from nights and revenue. Billed, paid and outstanding come from checked-in guests\' folios. Currency:') . ' ' . $this->hotelCurrency()"
>
    <table class="w-full text-sm text-zinc-700 dark:text-zinc-300">
        <thead class="border-b border-zinc-200 dark:border-zinc-700">
            <tr>
                <th class="{{ $th }} text-left">{{ __('Agent') }}</th>
                <th class="{{ $th }} text-right">{{ __('Bookings') }}</th>
                <th class="{{ $th }} text-right">{{ __('Cancelled / No-show') }}</th>
                <th class="{{ $th }} text-right">{{ __('Room Nights') }}</th>
                <th class="{{ $th }} text-right">{{ __('Room Revenue') }}</th>
                <th class="{{ $th }} text-right">{{ __('Billed') }}</th>
                <th class="{{ $th }} text-right">{{ __('Paid') }}</th>
                <th class="{{ $th }} text-right">{{ __('Outstanding') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($this->rows as $row)
                <tr wire:key="agent-{{ $row['agent']->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                    <td class="{{ $td }} font-medium text-zinc-900 dark:text-zinc-100">
                        <a href="{{ route('agents.edit', $row['agent']) }}" wire:navigate class="hover:underline">{{ $row['agent']->name }}</a>
                    </td>
                    <td class="{{ $td }} text-right tabular-nums">{{ number_format($row['bookings']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums text-zinc-500">{{ number_format($row['lost']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ number_format($row['room_nights']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($row['room_revenue']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($row['billed']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums text-green-600 dark:text-green-400">{{ $amount($row['paid']) }}</td>
                    <td @class([$td, 'text-right font-medium tabular-nums', 'text-red-600 dark:text-red-400' => $row['balance']->isPositive()])>{{ $amount($row['balance']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-4 py-12 text-center text-zinc-500">{{ __('No agent bookings arriving in this period.') }}</td>
                </tr>
            @endforelse
        </tbody>
        @if ($this->rows !== [])
            <tfoot class="border-t-2 border-zinc-200 font-semibold text-zinc-900 dark:border-zinc-700 dark:text-zinc-100">
                <tr>
                    <td class="{{ $td }} uppercase">{{ __('Total') }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ number_format($totals['bookings']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ number_format($totals['lost']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ number_format($totals['room_nights']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($totals['room_revenue']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($totals['billed']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($totals['paid']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($totals['balance']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</x-reports.shell>
