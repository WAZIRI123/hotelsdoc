<section class="w-full">
    <div class="pb-6">
        <flux:heading size="xl">{{ __('Point of sale') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Choose an outlet to start taking orders.') }}</flux:text>
    </div>

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
</section>
