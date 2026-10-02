<x-layouts::app :title="__('Manage Stock').' — '.$outlet->name">
    @php
        $cards = [
            [
                'permission' => 'manage-stock',
                'url' => route('suppliers.create', ['outlet' => $outlet->id]),
                'label' => __('Add Supplier'),
                'icon' => 'document-duplicate',
                'color' => 'bg-[#6495ED]',
            ],
            [
                'permission' => 'manage-stock',
                'url' => route('suppliers.index', ['outlet' => $outlet->id]),
                'label' => __('View Supplier'),
                'icon' => 'folder',
                'color' => 'bg-[#EE82EE]',
            ],
            [
                'permission' => 'manage-stock',
                'url' => route('stock-types.create', ['outlet' => $outlet->id]),
                'label' => __('Add Stock Type'),
                'icon' => 'cube',
                'color' => 'bg-[#5F9EA0]',
            ],
            [
                'permission' => 'manage-stock',
                'url' => route('stock-types.index', ['outlet' => $outlet->id]),
                'label' => __('View Stock Type'),
                'icon' => 'folder',
                'color' => 'bg-[#F4A460]',
            ],
            [
                'permission' => 'manage-outlets',
                'url' => route('menu.index', $outlet),
                'label' => __('Add Categories'),
                'icon' => 'pencil-square',
                'color' => 'bg-[#BC8F8F]',
            ],
            [
                'permission' => 'manage-outlets',
                'url' => route('menu.index', $outlet),
                'label' => __('View Categories'),
                'icon' => 'folder',
                'color' => 'bg-[#008000]',
            ],
            [
                'permission' => 'manage-outlets',
                'url' => route('menu.index', $outlet),
                'label' => __('Add Products'),
                'icon' => 'cog-6-tooth',
                'color' => 'bg-[#F4A460]',
            ],
            [
                'permission' => 'view-reports',
                'url' => route('reports.stock', ['outlet' => $outlet->id, 'view' => 'on-hand']),
                'label' => __('View Products'),
                'icon' => 'folder',
                'color' => 'bg-[#6495ED]',
            ],
            [
                'permission' => 'view-reports',
                'url' => route('reports.stock', ['outlet' => $outlet->id]),
                'label' => __('Receive Stock'),
                'icon' => 'arrow-down-tray',
                'color' => 'bg-[#5F9EA0]',
            ],
            [
                'permission' => 'manage-stock',
                'url' => route('manage-stock.issue', $outlet),
                'label' => __('Issue Products'),
                'icon' => 'folder',
                'color' => 'bg-[#EE82EE]',
            ],
            [
                'permission' => 'view-reports',
                'url' => route('reports.stock', ['outlet' => $outlet->id, 'view' => 'issued']),
                'label' => __('Issued Stock'),
                'icon' => 'document-duplicate',
                'color' => 'bg-[#BC8F8F]',
            ],
        ];
    @endphp

    <section class="w-full">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center gap-3">
                <flux:icon.archive-box class="size-7 text-zinc-900 dark:text-zinc-100" />
                <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Manage Stock') }} — {{ $outlet->name }}</flux:heading>
            </div>

            <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <a href="{{ route('manage-stock') }}" wire:navigate
                    class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Change outlet') }}</a>
                @can('manage-outlets')
                    <span class="text-zinc-400 dark:text-zinc-600">|</span>
                    <a href="{{ route('outlets.index') }}" wire:navigate
                        class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Outlets') }}</a>
                @endcan
            </nav>

            <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($cards as $card)
                    @can($card['permission'])
                        <a href="{{ $card['url'] }}" wire:navigate
                            class="{{ $card['color'] }} flex items-center justify-center gap-4 rounded-lg border-2 border-white/25 px-6 py-6 text-white shadow-md transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-white/60 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                            <flux:icon :name="$card['icon']" class="size-8 flex-shrink-0" />
                            <span class="text-lg font-medium">{{ $card['label'] }}</span>
                        </a>
                    @endcan
                @endforeach
            </div>
        </div>
    </section>
</x-layouts::app>
