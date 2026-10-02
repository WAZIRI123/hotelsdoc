<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.chart-bar class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">
                {{ $this->roleTitle() }} {{ __('Reports') }}
            </flux:heading>
        </div>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        @if ($this->reports === [])
            <flux:text>{{ __('You do not have access to any reports.') }}</flux:text>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($this->reports as $key => $report)
                    <a href="{{ route($report['route'], $report['params']) }}" wire:navigate data-report="{{ $key }}"
                        class="{{ $report['color'] }} flex items-center justify-center gap-4 rounded-lg border-2 border-white/25 px-6 py-8 text-white shadow-md transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-white/60 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                        <flux:icon :name="$report['icon']" class="size-12 flex-shrink-0" />
                        <span class="text-lg font-semibold">{{ __($report['label']) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>
