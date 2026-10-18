@php
    $amount = fn (\App\Support\Money $money): string => number_format($money->toMajor(), 2);
    $quantity = fn (string $value): string => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
@endphp

<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.folder class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('View Daily Expenses') }}</flux:heading>

            <a href="{{ route('expenses.create') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Add Daily Expenses') }}
            </a>
            <a href="{{ route('expenses') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Expenses Panel') }}
            </a>
        </div>

        @include('livewire.expenses.partials.nav')

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <div class="mb-5 flex flex-wrap items-end gap-4">
            <div class="w-full sm:w-44">
                <flux:input type="date" wire:model.live="from" :label="__('From')" />
            </div>
            <div class="w-full sm:w-44">
                <flux:input type="date" wire:model.live="to" :label="__('To')" />
            </div>
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="category" :label="__('Category')">
                    <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                    @foreach ($this->categories as $option)
                        <flux:select.option :value="$option->name">{{ $option->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="ms-auto text-right">
                <flux:text>{{ __(':count expenses', ['count' => $this->expenses->count()]) }}</flux:text>
                <div class="text-2xl font-semibold tabular-nums text-red-600 dark:text-red-400">
                    {{ $this->total->currency }} {{ $amount($this->total) }}
                </div>
            </div>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('PV Number') }}</flux:table.column>
                <flux:table.column>{{ __('Item') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Quantity') }}</flux:table.column>
                <flux:table.column>{{ __('Paid to') }}</flux:table.column>
                <flux:table.column>{{ __('Paid by') }}</flux:table.column>
                <flux:table.column>{{ __('Recorded by') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->expenses as $expense)
                    <flux:table.row :key="$expense->id">
                        <flux:table.cell class="whitespace-nowrap">{{ $expense->expense_date->format('d-m-Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $expense->pv_number ?? '—' }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $expense->description }}</flux:table.cell>
                        <flux:table.cell>{{ $expense->category }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $expense->quantity !== null ? $quantity($expense->quantity) : '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $expense->payee ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $expense->method_code !== null ? ucwords(str_replace('_', ' ', $expense->method_code)) : '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $expense->recorder?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $amount($expense->money()) }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" :href="route('expenses.edit', $expense)" wire:navigate>
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger"
                                        wire:click="delete({{ $expense->id }})"
                                        wire:confirm="{{ __('Delete this expense?') }}">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="10" class="text-center text-zinc-500">
                            {{ __('No expenses in this period.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
