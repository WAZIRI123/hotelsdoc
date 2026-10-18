<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.cube class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">
                {{ $expense ? __('Edit Daily Expense') : __('Add Daily Expenses') }}
            </flux:heading>

            <a href="{{ route('expenses.list') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('View Daily Expenses') }}
            </a>
            <a href="{{ route('expenses') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Expenses Panel') }}
            </a>
        </div>

        @include('livewire.expenses.partials.nav')

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <form wire:submit="save" class="max-w-3xl space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="expenseDate" :label="__('Date')" :max="now()->toDateString()" required />
                <flux:input wire:model="pvNumber" :label="__('PV Number')" :placeholder="__('Payment voucher number')" />
            </div>

            <flux:field>
                <flux:label>{{ __('Category') }} <span class="text-red-500">*</span></flux:label>
                <flux:select wire:model="category" required>
                    <flux:select.option value="">{{ __('Choose a category') }}</flux:select.option>
                    @foreach ($this->categories as $option)
                        <flux:select.option :value="$option->name">{{ $option->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="category" />
                <flux:description>
                    {{ __('Missing a category?') }}
                    <a href="{{ route('expense-categories.create') }}" wire:navigate class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Add one') }}</a>
                </flux:description>
            </flux:field>

            <flux:input wire:model="description" :label="__('Item')" :placeholder="__('e.g. Taxi to the market')" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="number" min="0" step="any" wire:model="quantity" :label="__('Quantity')" />
                <flux:input type="number" min="0" step="any" wire:model="amount"
                    :label="__('Amount (:currency)', ['currency' => $this->currency()])" required />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="payee" :label="__('Paid to')" />
                <flux:select wire:model="method" :label="__('Paid by')">
                    <flux:select.option value="">{{ __('Not specified') }}</flux:select.option>
                    @foreach ($this->paymentMethods as $paymentMethod)
                        <flux:select.option :value="$paymentMethod->code">{{ $paymentMethod->label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('expenses.list')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" icon="check">
                    {{ $expense ? __('Save Expense') : __('Record Expense') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>
