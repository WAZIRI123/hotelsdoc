@php
    $amount = fn (\App\Support\Money $money): string => number_format($money->toMajor(), 2);
    $quantity = fn (string $value): string => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
    $td = 'px-4 py-3 whitespace-nowrap';
    $canRecord = $this->canRecord();
    $topCategory = array_key_first($this->totalsByCategory);
@endphp

<div>
    <x-reports.shell
        :heading="__('Expenses Report')"
        icon="receipt-percent"
        :subtitle="__('Expenses from :from to :to', ['from' => $this->periodStart()->format('d M Y'), 'to' => $this->periodEnd()->format('d M Y')])"
        :tiles="[
            [__('Expenses'), $this->expenses->count(), 'text-zinc-900 dark:text-zinc-100'],
            [__('Categories'), count($this->totalsByCategory), 'text-zinc-900 dark:text-zinc-100'],
            [__('Biggest Category'), $topCategory ?? '—', 'text-zinc-900 dark:text-zinc-100 !text-base'],
            [__('Total Spent'), $amount($this->grandTotal), 'text-red-600 dark:text-red-400'],
        ]"
        :footnote="__('Currency:') . ' ' . $this->hotelCurrency()"
    >
        @if ($canRecord)
            <x-slot:actions>
                <button type="button" wire:click="openCreate"
                    class="inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 print:hidden dark:focus:ring-offset-zinc-900">
                    <flux:icon.plus variant="mini" class="size-4" />
                    {{ __('Record Expense') }}
                </button>
            </x-slot:actions>
        @endif

        <x-slot:filters>
            <div class="w-full sm:w-56">
                <flux:select wire:model="category" :label="__('Category')">
                    <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                    @foreach (\App\Models\Expense::CATEGORIES as $option)
                        <flux:select.option :value="$option">{{ __($option) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </x-slot:filters>

        <table class="w-full text-sm text-zinc-700 dark:text-zinc-300">
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60">
                <tr>
                    <th class="{{ $th }} text-left">{{ __('Date') }}</th>
                    <th class="{{ $th }} text-left">{{ __('PV Number') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Item') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Category') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Quantity') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Amount') }}</th>
                    @if ($canRecord)
                        <th class="{{ $th }} print:hidden"></th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->expenses as $expense)
                    <tr wire:key="expense-{{ $expense->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                        <td class="{{ $td }}">{{ $expense->expense_date->format('d-m-Y') }}</td>
                        <td class="{{ $td }}">{{ $expense->pv_number ?? '—' }}</td>
                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $expense->description }}</td>
                        <td class="{{ $td }}">{{ __($expense->category) }}</td>
                        <td class="{{ $td }} tabular-nums">{{ $expense->quantity !== null ? $quantity($expense->quantity) : '—' }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ $amount($expense->money()) }}</td>
                        @if ($canRecord)
                            <td class="{{ $td }} text-right print:hidden">
                                <button type="button" wire:click="delete({{ $expense->id }})" wire:confirm="{{ __('Delete this expense?') }}"
                                    class="text-red-600 hover:underline dark:text-red-400">{{ __('Delete') }}</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canRecord ? 7 : 6 }}" class="px-4 py-12 text-center text-zinc-500">{{ __('No expenses in this period.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-100">
                <tr>
                    <td class="{{ $td }}"></td>
                    <td colspan="4" class="{{ $td }} text-base">{{ __('Total') }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($this->grandTotal) }}</td>
                    @if ($canRecord)
                        <td class="print:hidden"></td>
                    @endif
                </tr>
            </tfoot>
        </table>
    </x-reports.shell>

    @if ($canRecord)
        <flux:modal name="expense-form" class="md:w-[480px]">
            <form wire:submit="save" class="space-y-6">
                <flux:heading size="lg">{{ __('Record expense') }}</flux:heading>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="date" wire:model="expenseDate" :label="__('Date')" required />
                    <flux:input wire:model="expensePvNumber" :label="__('PV Number')" />
                </div>
                <flux:select wire:model="expenseCategory" :label="__('Category')" required>
                    <flux:select.option value="">{{ __('Choose a category') }}</flux:select.option>
                    @foreach (\App\Models\Expense::CATEGORIES as $option)
                        <flux:select.option :value="$option">{{ __($option) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="expenseDescription" :label="__('Item')" required />
                <flux:input type="number" min="0" step="any" wire:model="expenseQuantity" :label="__('Quantity')" />
                <flux:input wire:model="expensePayee" :label="__('Paid to')" />
                <flux:select wire:model="expenseMethod" :label="__('Paid by')">
                    <flux:select.option value="">{{ __('Not specified') }}</flux:select.option>
                    @foreach ($this->paymentMethods as $method)
                        <flux:select.option :value="$method->code">{{ $method->label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="number" min="0" step="any" wire:model="expenseAmount"
                    :label="__('Amount (:currency)', ['currency' => $this->hotelCurrency()])" required />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
