<x-layouts::app :title="__('Manage Stock')">
    @php
        $colors = ['bg-[#6495ED]', 'bg-[#EE82EE]', 'bg-[#5F9EA0]', 'bg-[#F4A460]', 'bg-[#BC8F8F]', 'bg-[#008000]'];

        $icons = [
            'bar' => 'beaker',
            'restaurant' => 'cake',
            'room_service' => 'bell-alert',
            'shop' => 'shopping-bag',
        ];
    @endphp

    <section class="w-full">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center gap-3">
                <flux:icon.archive-box class="size-7 text-zinc-900 dark:text-zinc-100" />
                <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Manage Stock') }}</flux:heading>
            </div>

            <flux:text class="mt-2">{{ __('Select the outlet whose stock you want to manage.') }}</flux:text>

            <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($outlets as $outlet)
                    <a href="{{ route('manage-stock.outlet', $outlet) }}" wire:navigate wire:key="outlet-{{ $outlet->id }}"
                        class="{{ $colors[$loop->index % count($colors)] }} {{ $outlet->is_active ? '' : 'opacity-60' }} flex items-center justify-center gap-4 rounded-lg border-2 border-white/25 px-6 py-6 text-white shadow-md transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-white/60 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                        <flux:icon :name="$icons[$outlet->type] ?? 'building-storefront'" class="size-8 flex-shrink-0" />
                        <span class="flex flex-col">
                            <span class="text-lg font-medium">{{ $outlet->name }}</span>
                            <span class="text-sm text-white/80">
                                {{ ucwords(str_replace('_', ' ', $outlet->type)) }}@unless ($outlet->is_active) · {{ __('Inactive') }}@endunless
                            </span>
                        </span>
                    </a>
                @endforeach

                @can('manage-outlets')
                    <a href="{{ route('outlets.index') }}" wire:navigate
                        class="flex items-center justify-center gap-4 rounded-lg border-2 border-dashed border-zinc-300 px-6 py-6 text-zinc-600 transition hover:bg-zinc-50 focus:outline-none focus:ring-2 focus:ring-zinc-400 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        <flux:icon.building-storefront class="size-8 flex-shrink-0" />
                        <span class="text-lg font-medium">{{ $outlets->isEmpty() ? __('Add your first outlet') : __('Add / edit outlets') }}</span>
                    </a>
                @endcan
            </div>

            @if ($outlets->isEmpty())
                <flux:text class="mt-4 text-center">{{ __('No outlets yet. Add a bar, restaurant or shop to start managing stock.') }}</flux:text>
            @endif
        </div>
    </section>
</x-layouts::app>
