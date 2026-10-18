<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.document-duplicate class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">
                {{ $category ? __('Edit Expenses Category') : __('Add Expenses Category') }}
            </flux:heading>

            <a href="{{ route('expense-categories.index') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('View Expenses Category') }}
            </a>
            <a href="{{ route('expenses') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Expenses Panel') }}
            </a>
        </div>

        @include('livewire.expenses.partials.nav')

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <form wire:submit="save" class="max-w-3xl space-y-4">
            <flux:field>
                <flux:label>{{ __('Category Name') }} <span class="text-red-500">*</span></flux:label>
                <flux:input wire:model="name" :placeholder="__('e.g. Transport')" required autofocus />
                <flux:error name="name" />
            </flux:field>
            <flux:input wire:model="description" :label="__('Description')" :placeholder="__('e.g. Fuel, taxi and bus fares')" />

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('expense-categories.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" icon="check">
                    {{ $category ? __('Save Category') : __('Add Category') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>
