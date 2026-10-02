@php
    $amount = fn (\App\Support\Money $money): string => number_format($money->toMajor(), 0);
    $reportFrom = $this->reportDay()->format('d M Y');
    $reportTo = $this->reportEnd()->format('d M Y');
    $currencyLabel = $currency !== '' ? $currency : __('All currencies');
    $tabs = array_intersect_key([
        'reservations' => __('In/Out Reservations'),
        'staff' => __('Staff Payments'),
        'general' => __('General Payments'),
    ], array_flip($this->allowedViews()));
    $heading = match ($view) {
        'staff' => __('Reservation Staff Payment Report'),
        'general' => __('Reservation General Payment Report'),
        default => __('In/Out Reservation Reports'),
    };
    $statusBadge = fn (string $status): string => match ($status) {
        'Checked In' => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
        'Checked Out' => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
        'Confirmed' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        'Hold' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        default => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
    };
    $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
    $td = 'px-4 py-3 whitespace-nowrap';
    $tiles = $view === 'reservations'
        ? [
            [__('Reservations'), count($this->reservationRows), 'text-zinc-900 dark:text-zinc-100'],
            [__('Total'), $amount($this->reservationTotals['total']), 'text-zinc-900 dark:text-zinc-100'],
            [__('Paid'), $amount($this->reservationTotals['paid']), 'text-green-600 dark:text-green-400'],
            [__('Balance'), $amount($this->reservationTotals['balance']), $this->reservationTotals['balance']->isPositive() ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-zinc-100'],
        ]
        : [
            [__('Payments'), $this->payments->count(), 'text-zinc-900 dark:text-zinc-100'],
            [$view === 'staff' ? __('Staff') : __('Methods'), count($view === 'staff' ? $this->totalsByStaff : $this->totalsByMethod), 'text-zinc-900 dark:text-zinc-100'],
            [__('Collected'), $amount($this->grandTotal), 'text-green-600 dark:text-green-400'],
        ];
@endphp

<section class="w-full space-y-3">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm print:border-0 print:p-0 print:shadow-none dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.calendar-days class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ $heading }}</flux:heading>

            <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 print:hidden dark:focus:ring-offset-zinc-900">
                <flux:icon.printer variant="mini" class="size-4" />
                {{ __('Print') }}
            </button>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm print:hidden">
            <a href="{{ route('reports.index') }}" wire:navigate class="text-sky-600 hover:underline dark:text-sky-400">{{ __('All reports') }}</a>
            @foreach ($tabs as $key => $label)
                <span class="text-zinc-400 dark:text-zinc-600">|</span>
                <button type="button" wire:click="showView('{{ $key }}')"
                    @class([
                        'hover:underline',
                        'font-semibold text-zinc-900 dark:text-zinc-100' => $view === $key,
                        'text-sky-600 dark:text-sky-400' => $view !== $key,
                    ])>{{ $label }}</button>
            @endforeach
        </nav>

        <hr class="my-5 border-zinc-200 print:hidden dark:border-zinc-700" />

        <form wire:submit="getReport" class="flex flex-wrap items-end gap-4 print:hidden">
            <div class="w-full sm:w-56">
                <flux:input type="date" wire:model="date" :label="__('From')" />
            </div>
            <div class="w-full sm:w-56">
                <flux:input type="date" wire:model="to" :label="__('To')" />
            </div>
            <div class="w-full sm:w-56">
                <flux:select wire:model="currency" :label="__('Currency')">
                    <flux:select.option value="">{{ __('All currencies') }}</flux:select.option>
                    @foreach ($this->currencies as $code)
                        <flux:select.option :value="$code">{{ $code }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <button type="submit"
                class="inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                <flux:icon.arrow-path variant="mini" class="size-4" wire:loading.class="animate-spin" wire:target="getReport" />
                {{ __('Get Report') }}
            </button>
        </form>

        <flux:text class="mt-6">
            {{ __('Report from') }}
            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $reportFrom }}</span>
            {{ __('to') }}
            <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $reportTo }}</span>
            · {{ $currencyLabel }}
        </flux:text>

        <div @class(['mt-3 grid grid-cols-2 gap-3', 'lg:grid-cols-4' => count($tiles) === 4, 'lg:grid-cols-3' => count($tiles) === 3])>
            @foreach ($tiles as [$label, $value, $color])
                <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-semibold tabular-nums {{ $color }}">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm print:shadow-none dark:border-zinc-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-zinc-700 dark:text-zinc-300">
                @if ($view === 'reservations')
                    <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <tr>
                            <th class="{{ $th }} text-left">{{ __('Customer') }}</th>
                            <th class="{{ $th }} text-left">{{ __('Room') }}</th>
                            <th class="{{ $th }} text-left">{{ __('Check-in') }}</th>
                            <th class="{{ $th }} text-left">{{ __('Stay') }}</th>
                            <th class="{{ $th }} text-right">{{ __('Per Night') }}</th>
                            <th class="{{ $th }} text-right">{{ __('Discount') }}</th>
                            <th class="{{ $th }} text-right">{{ __('Total') }}</th>
                            <th class="{{ $th }} text-left">{{ __('Paid By') }}</th>
                            <th class="{{ $th }} text-right">{{ __('Paid') }}</th>
                            <th class="{{ $th }} text-right">{{ __('Balance') }}</th>
                            <th class="{{ $th }} text-left">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->reservationRows as $row)
                            <tr wire:key="reservation-row-{{ $loop->index }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                <td class="{{ $td }} font-medium text-zinc-900 dark:text-zinc-100">{{ $row['customer'] }}</td>
                                <td class="{{ $td }}">{{ $row['rooms'] }}</td>
                                <td class="{{ $td }}">{{ $row['check_in'] }}</td>
                                <td class="{{ $td }}">{{ trans_choice(':count night|:count nights', $row['nights']) }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $amount($row['per_night']) }}</td>
                                <td class="{{ $td }} text-right tabular-nums text-zinc-400">{{ $row['discount']->isZero() ? '—' : $amount($row['discount']) }}</td>
                                <td class="{{ $td }} text-right font-medium tabular-nums text-zinc-900 dark:text-zinc-100">{{ $amount($row['total']) }}</td>
                                <td class="{{ $td }}">{{ $row['paid_by'] !== '' ? $row['paid_by'] : '—' }}</td>
                                <td class="{{ $td }} text-right tabular-nums text-green-600 dark:text-green-400">{{ $amount($row['paid']) }}</td>
                                <td @class([$td, 'text-right font-medium tabular-nums', 'text-red-600 dark:text-red-400' => $row['balance']->isPositive()])>{{ $amount($row['balance']) }}</td>
                                <td class="{{ $td }}">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadge($row['status']) }}">{{ $row['status'] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center text-zinc-500">
                                    <flux:icon.calendar-days class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                                    {{ __('No reservations in this period.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (count($this->reservationRows) > 0)
                        <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-100">
                            <tr>
                                <td colspan="6" class="{{ $td }}">{{ __('Total') }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $amount($this->reservationTotals['total']) }}</td>
                                <td class="{{ $td }}"></td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $amount($this->reservationTotals['paid']) }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $amount($this->reservationTotals['balance']) }}</td>
                                <td class="{{ $td }}"></td>
                            </tr>
                        </tfoot>
                    @endif
                @else
                    @php($groups = $view === 'staff' ? $this->totalsByStaff : $this->totalsByMethod)
                    <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60">
                        <tr>
                            <th class="{{ $th }} text-left">{{ $view === 'staff' ? __('Staff') : __('Payment Method') }}</th>
                            <th class="{{ $th }} text-right">{{ __('Payments') }}</th>
                            <th class="{{ $th }} text-right">{{ __('Total') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($groups as $key => $group)
                            <tr wire:key="group-{{ $view }}-{{ $key }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                                <td class="{{ $td }} font-medium text-zinc-900 dark:text-zinc-100">{{ $group['label'] }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $group['count'] }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $amount($group['total']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-12 text-center text-zinc-500">
                                    <flux:icon.banknotes class="mx-auto mb-2 size-8 text-zinc-300 dark:text-zinc-600" />
                                    {{ __('No payments in this period.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (count($groups) > 0)
                        <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-100">
                            <tr>
                                <td class="{{ $td }}">{{ __('Total') }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $this->payments->count() }}</td>
                                <td class="{{ $td }} text-right tabular-nums">{{ $amount($this->grandTotal) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                @endif
            </table>
        </div>

        <div class="flex flex-wrap justify-between gap-2 border-t border-zinc-200 px-4 py-3 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            <span>{{ __('Printed by:') }} {{ auth()->user()->name }} · {{ now()->format('d M Y, H:i') }}</span>
            <span>{{ __('Currency:') }} {{ $currencyLabel }}</span>
        </div>
    </div>
</section>
