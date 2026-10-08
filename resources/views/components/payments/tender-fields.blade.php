{{--
    Currency + amount inputs for a component using App\Livewire\Concerns\TakesTender.
    mustCover: the payment has to cover everything due (orders), rather than allowing a part payment.
--}}
@props(['currencies', 'summary', 'mustCover' => true, 'amountLabel' => null])

@php
    $local = \App\Support\HotelCurrency::current();
    $tender = $summary['tender'];
@endphp

<div {{ $attributes->class('space-y-2') }}>
    <div class="grid gap-3 sm:grid-cols-2">
        <flux:select wire:model.live="tenderCurrency" :label="__('Paid in')">
            <flux:select.option value="">{{ $local }} ({{ __('local') }})</flux:select.option>
            @foreach ($currencies as $currency)
                <flux:select.option :value="$currency->code">
                    {{ $currency->code }} — 1 = {{ $local }} {{ number_format((float) $currency->rate, 2) }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:input wire:model.live.debounce.400ms="tenderAmount" type="number" min="0" step="any"
            :label="$amountLabel ?? __('Amount received (:currency)', ['currency' => $summary['dueInTender']->currency])"
            :placeholder="$summary['dueInTender']->toMajor() > 0 ? number_format($summary['dueInTender']->toMajor(), 2, '.', '') : ''" />
    </div>

    @if ($tender !== null)
        <div class="rounded-md bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800">
            @if ($tender->isForeign())
                <div class="flex justify-between gap-2">
                    <span class="text-zinc-500">{{ __('Value in :currency', ['currency' => $local]) }}</span>
                    <span class="font-mono">{{ $tender->amount->formatExact() }} ({{ $tender->localValue()->format() }})</span>
                </div>
            @endif
            <div class="flex justify-between gap-2">
                <span class="text-zinc-500">{{ __('Due') }}</span>
                <span class="font-mono">
                    {{ $summary['due']->format() }}
                    @if ($tender->isForeign())
                        <span class="text-zinc-500">≈ {{ $summary['dueInTender']->formatExact() }}</span>
                    @endif
                </span>
            </div>
            @if ($summary['short'] !== null)
                <div @class(['flex justify-between gap-2 font-medium', 'text-red-600 dark:text-red-400' => $mustCover, 'text-amber-600 dark:text-amber-400' => ! $mustCover])>
                    <span>{{ $mustCover ? __('Not enough — short by') : __('Part payment — still owed') }}</span>
                    <span class="font-mono">{{ $summary['short']->format() }}</span>
                </div>
            @elseif ($summary['change'] !== null)
                <div class="flex justify-between gap-2 font-medium text-green-700 dark:text-green-400">
                    <span>{{ __('Covers it — change') }}</span>
                    <span class="font-mono">{{ $summary['change']->format() }}</span>
                </div>
            @else
                <div class="font-medium text-green-700 dark:text-green-400">{{ __('Covers the amount due.') }}</div>
            @endif
        </div>
    @endif

    <flux:error name="tenderCurrency" />
    <flux:error name="tenderAmount" />
</div>
