<x-layouts::app :title="__('Organization Dashboard')">
    @php
        $cards = [
            [
                'route' => 'admin.organization.system-control',
                'label' => __('System Control'),
                'icon' => 'cog-6-tooth',
                'color' => 'bg-[#F08080]',
            ],
            [
                'route' => 'admin.hotels.index',
                'label' => __('View Details'),
                'icon' => 'document-text',
                'color' => 'bg-[#BC8F8F]',
            ],
            [
                'route' => 'admin.currencies.create',
                'label' => __('Add Currency'),
                'icon' => 'banknotes',
                'color' => 'bg-[#5F9EA0]',
            ],
            [
                'route' => 'admin.currencies.index',
                'label' => __('View Currency'),
                'icon' => 'currency-dollar',
                'color' => 'bg-[#6495ED]',
            ],
            [
                'route' => 'admin.invoice-footers.create',
                'label' => __('Add Invoice Footer'),
                'icon' => 'pencil',
                'color' => 'bg-[#A52A2A]',
            ],
            [
                'route' => 'admin.invoice-footers.index',
                'label' => __('View Invoice Footer'),
                'icon' => 'document-duplicate',
                'color' => 'bg-[#6495ED]',
            ],
        ];
    @endphp

    <section class="w-full">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center gap-3">
                <flux:icon.information-circle class="size-7 text-zinc-900 dark:text-zinc-100" />
                <flux:heading size="xl" class="!text-3xl !font-normal">
                    {{ __('Organization Dashboard') }}
                </flux:heading>
            </div>

            <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($cards as $card)
                    <a href="{{ route($card['route']) }}" wire:navigate
                        class="{{ $card['color'] }} flex items-center justify-center gap-4 rounded-lg border-2 border-white/25 px-6 py-6 text-white shadow-md transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-white/60 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                        <flux:icon :name="$card['icon']" class="size-8 flex-shrink-0" />
                        <span class="text-lg font-medium">{{ $card['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts::app>
