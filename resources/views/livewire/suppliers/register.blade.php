<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.truck class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">
                {{ $supplier ? __('Edit Supplier') : __('Add Supplier') }}
            </flux:heading>

            <a href="{{ route('suppliers.index', array_filter(['outlet' => $outletId])) }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('View Supplier') }}
            </a>
            <a href="{{ $outletId ? route('manage-stock.outlet', $outletId) : route('manage-stock') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Manage Stock') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('suppliers.index', array_filter(['outlet' => $outletId])) }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('All Suppliers') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('stock-types.create', array_filter(['outlet' => $outletId])) }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Add Stock Type') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-x-10 gap-y-4 md:grid-cols-2">
                <div class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Supplier Name') }} <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="name" :placeholder="__('e.g. Serengeti Breweries Ltd')" required autofocus />
                        <flux:error name="name" />
                    </flux:field>
                    <flux:input wire:model="contact_person" :label="__('Contact person')" :placeholder="__('Who you deal with')" />
                    <flux:input wire:model="phone" :label="__('Phone')" :placeholder="__('Supplier\'s phone')" />
                </div>

                <div class="space-y-4">
                    <flux:input wire:model="email" type="email" :label="__('Email')" :placeholder="__('Supplier\'s email')" />
                    <flux:input wire:model="tin" :label="__('TIN')" :placeholder="__('Supplier\'s TIN number')" />
                    <flux:input wire:model="address" :label="__('Address')" :placeholder="__('Supplier\'s address')" />

                    @if ($supplier)
                        <flux:switch wire:model="is_active" :label="__('Active')"
                            :description="__('Inactive suppliers are hidden when recording stock received.')" />
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('suppliers.index', array_filter(['outlet' => $outletId]))" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" icon="check">
                    {{ $supplier ? __('Save Supplier') : __('Add Supplier') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>
