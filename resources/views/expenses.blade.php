<x-layouts::app :title="__('Expenses Panel')">
    @php
        $cards = [
            ['route' => 'expense-categories.create', 'label' => __('Add Expenses Category'), 'icon' => 'document-duplicate', 'color' => 'bg-[#6495ED]'],
            ['route' => 'expense-categories.index', 'label' => __('View Expenses Category'), 'icon' => 'folder', 'color' => 'bg-[#EE82EE]'],
            ['route' => 'expenses.create', 'label' => __('Add Daily Expenses'), 'icon' => 'cube', 'color' => 'bg-[#5F9EA0]'],
            ['route' => 'expenses.list', 'label' => __('View Daily Expenses'), 'icon' => 'folder', 'color' => 'bg-[#F4A460]'],
        ];
    @endphp

    <section class="w-full">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center gap-3">
                <flux:icon.cog-6-tooth class="size-7 text-zinc-900 dark:text-zinc-100" />
                <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Expenses Panel') }}</flux:heading>
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

            @can('view-reports')
                <flux:text class="mt-6">
                    {{ __('Every expense recorded here also appears in the') }}
                    <a href="{{ route('reports.expenses') }}" wire:navigate class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Expenses Report') }}</a>.
                </flux:text>
            @endcan
        </div>
    </section>
</x-layouts::app>
