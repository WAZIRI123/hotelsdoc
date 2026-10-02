@props([
    'heading',
    'icon' => 'chart-bar',
    'tiles' => [],
    'subtitle' => null,
    'period' => true,
    'footnote' => null,
])

{{-- Shared page frame for the Get Reports pages: header, period filter, summary tiles and the report card. --}}
<section class="w-full space-y-3">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm print:border-0 print:p-0 print:shadow-none dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon :name="$icon" class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ $heading }}</flux:heading>

            <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 print:hidden dark:focus:ring-offset-zinc-900">
                <flux:icon.printer variant="mini" class="size-4" />
                {{ __('Print') }}
            </button>

            {{ $actions ?? '' }}
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm print:hidden">
            <a href="{{ route('reports.index') }}" wire:navigate class="text-sky-600 hover:underline dark:text-sky-400">{{ __('All reports') }}</a>
            @isset($nav)
                <span class="text-zinc-400 dark:text-zinc-600">|</span>
                {{ $nav }}
            @endisset
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700 print:hidden" />

        <form wire:submit="getReport" class="flex flex-wrap items-end gap-3 print:hidden">
            @if ($period)
                <div class="w-full sm:w-56">
                    <flux:input type="date" wire:model="from" :label="__('From')" />
                </div>
                <div class="w-full sm:w-56">
                    <flux:input type="date" wire:model="to" :label="__('To')" />
                </div>
            @endif

            {{ $filters ?? '' }}

            <button type="submit"
                class="inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                <flux:icon.arrow-path variant="mini" class="size-4" wire:loading.class="animate-spin" wire:target="getReport" />
                {{ __('Get Report') }}
            </button>
        </form>

        @if ($subtitle)
            <flux:text class="mt-6">{{ $subtitle }}</flux:text>
        @endif

        @if ($tiles !== [])
            <div @class(['mt-3 grid grid-cols-2 gap-3', 'lg:grid-cols-4' => count($tiles) >= 4, 'lg:grid-cols-3' => count($tiles) === 3])>
                @foreach ($tiles as [$label, $value, $color])
                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</div>
                        <div class="mt-1 text-2xl font-semibold tabular-nums {{ $color }}">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm print:shadow-none dark:border-zinc-700 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            {{ $slot }}
        </div>

        <div class="flex flex-wrap justify-between gap-2 border-t border-zinc-200 px-4 py-3 text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            <span>{{ __('Printed by:') }} {{ auth()->user()->name }} · {{ now()->format('d M Y, H:i') }}</span>
            @if ($footnote)
                <span>{{ $footnote }}</span>
            @endif
        </div>
    </div>
</section>
