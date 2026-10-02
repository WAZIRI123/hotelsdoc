<section class="w-full">
    <div class="flex items-center justify-between gap-4 pb-4">
        <div>
            <flux:heading size="xl">{{ $ratePlan->name }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Base rate :price/night. Override any date below.', ['price' => $ratePlan->price->format()]) }}
            </flux:text>
        </div>

        <flux:button variant="ghost" icon="arrow-left" :href="route('rate-plans.index')" wire:navigate>
            {{ __('Back to rates') }}
        </flux:button>
    </div>

    <div class="flex items-center gap-3 pb-4">
        <flux:button variant="ghost" icon="chevron-left" wire:click="prevMonth" />
        <flux:heading size="lg" class="min-w-[10rem] text-center">
            {{ \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->format('F Y') }}
        </flux:heading>
        <flux:button variant="ghost" icon="chevron-right" wire:click="nextMonth" />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Date') }}</flux:table.column>
            <flux:table.column>{{ __('Day') }}</flux:table.column>
            <flux:table.column>{{ __('Effective rate') }}</flux:table.column>
            <flux:table.column>{{ __('Min stay') }}</flux:table.column>
            <flux:table.column>{{ __('Stop sell') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->days as $day)
                <flux:table.row :key="$day['date']" class="{{ $day['isWeekend'] ? 'bg-zinc-50 dark:bg-zinc-800/40' : '' }}">
                    <flux:table.cell class="font-mono text-sm">{{ $day['date'] }}</flux:table.cell>
                    <flux:table.cell>{{ $day['dow'] }}</flux:table.cell>
                    <flux:table.cell>
                        <span class="{{ $day['entry']?->price ? 'font-semibold' : 'text-zinc-500' }}">
                            {{ $day['effectivePrice']->format() }}
                        </span>
                        @if ($day['entry']?->price)
                            <flux:badge color="blue" size="sm">{{ __('Override') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $day['entry']?->min_stay ? __(':n nights', ['n' => $day['entry']->min_stay]) : '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($day['entry']?->stop_sell)
                            <flux:badge color="red" size="sm">{{ __('Closed') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Open') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:dropdown align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="openEdit('{{ $day['date'] }}')">
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                @if ($day['entry'])
                                    <flux:menu.item icon="arrow-uturn-left" variant="danger"
                                        wire:click="clear('{{ $day['date'] }}')"
                                        wire:confirm="{{ __('Remove override for this date?') }}">
                                        {{ __('Remove override') }}
                                    </flux:menu.item>
                                @endif
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="rate-calendar-form" class="md:w-[420px]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Override for :date', ['date' => $editingDate]) }}</flux:heading>
                <flux:text class="mt-2">
                    {{ __('Leave price empty to use the base rate. Toggle stop-sell to close bookings for this date.') }}
                </flux:text>
            </div>

            <flux:input wire:model.number="price_tsh" type="number" min="0"
                :label="__('Override price (:currency per night)', ['currency' => \App\Support\HotelCurrency::current()])" :placeholder="__('Leave empty for base rate')" />

            <flux:input wire:model.number="min_stay" type="number" min="1" max="60"
                :label="__('Minimum stay (nights)')" :placeholder="__('None')" />

            <flux:switch wire:model="stop_sell" :label="__('Stop sell — close bookings for this date')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
