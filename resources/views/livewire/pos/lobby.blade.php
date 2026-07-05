<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.computer-desktop class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Point of sale') }}</flux:heading>

            <a href="{{ route('outlets.index') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Manage Outlets') }}
            </a>

            <a href="{{ route('reports.cash-up') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Cash-up report') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('outlets.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Outlets') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('reports.cash-up') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Cash-up') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('guests.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Guest Book') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

    @if ($this->outlets->isEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('No active outlets') }}</flux:callout.heading>
            <flux:callout.text>
                <flux:link :href="route('outlets.index')" wire:navigate>{{ __('Create a bar or restaurant first') }}</flux:link>.
            </flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->outlets as $outlet)
                <a href="{{ route('pos.terminal', $outlet) }}" wire:navigate
                    class="block rounded-xl border border-zinc-200 bg-white p-6 transition hover:border-blue-500 hover:shadow dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ $outlet->name }}</flux:heading>
                    <flux:text class="mt-1">{{ ucwords(str_replace('_', ' ', $outlet->type)) }}</flux:text>
                </a>
            @endforeach
        </div>
    @endif
    </div>
</section>
