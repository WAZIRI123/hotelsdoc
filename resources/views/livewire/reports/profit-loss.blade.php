@php
    $amount = fn (\App\Support\Money $money): string => number_format($money->toMajor(), 0);
    $sales = $this->salesColumns;
    $net = $this->netProfit;
    $th = 'border border-zinc-300 px-4 py-3 text-right text-sm font-semibold text-zinc-800 dark:border-zinc-600 dark:text-zinc-200';
    $td = 'border border-zinc-300 px-4 py-3 text-right align-top tabular-nums dark:border-zinc-600';
@endphp

<x-reports.shell
    :heading="__('Profit & Loss Report')"
    icon="arrows-right-left"
    :subtitle="__('Profit & loss from :from to :to', ['from' => $this->periodStart()->format('d M Y'), 'to' => $this->periodEnd()->format('d M Y')]) . ' · ' . $this->hotelCurrency()"
    :tiles="[
        [__('Income'), $amount($this->totalIncome), 'text-green-600 dark:text-green-400'],
        [__('Expenses'), $amount($this->totalExpenses), 'text-red-600 dark:text-red-400'],
        [$net->isNegative() ? __('Net Loss') : __('Net Profit'), $amount($net), $net->isNegative() ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'],
    ]"
    :footnote="__('POS sales are before tax. Stock Purchases are stock received in the period (quantity × unit cost). Currency:') . ' ' . $this->hotelCurrency()"
>
    <div class="p-4">
        <table class="w-full border-collapse text-sm text-zinc-700 dark:text-zinc-300">
            <thead class="border-b-2 border-zinc-300 dark:border-zinc-600">
                <tr>
                    <th class="{{ $th }}">{{ __('Food & Drinks sales') }}</th>
                    <th class="{{ $th }}">{{ __('Accommodation sales') }}</th>
                    <th class="{{ $th }}">{{ __('Extras sales') }}</th>
                    <th class="{{ $th }}">{{ __('Total Expenses') }}</th>
                    <th class="{{ $th }}">{{ __('Expenses') }}</th>
                    <th class="{{ $th }}">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="{{ $td }}">{{ $amount($sales['food_drinks']) }}</td>
                    <td @class([$td, 'text-red-600 dark:text-red-400' => $sales['accommodation']->isNegative()])>{{ $amount($sales['accommodation']) }}</td>
                    <td class="{{ $td }}">{{ $amount($sales['extras']) }}</td>
                    <td class="{{ $td }}">{{ $amount($this->totalExpenses) }}</td>
                    <td class="{{ $td }}">
                        @foreach ($this->expenses as $category => $value)
                            <div wire:key="expense-{{ $loop->index }}" class="whitespace-nowrap">
                                <span class="text-zinc-500 dark:text-zinc-400">{{ __($category) }}:</span> {{ $amount($value) }}
                            </div>
                        @endforeach
                    </td>
                    <td @class([$td, 'font-semibold', 'text-red-600 dark:text-red-400' => $net->isNegative(), 'text-green-600 dark:text-green-400' => ! $net->isNegative()])>{{ $amount($net) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</x-reports.shell>
