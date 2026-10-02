<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.information-circle class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Add Currency') }}</flux:heading>

            <flux:spacer />

            <a href="{{ route('admin.currencies.index', array_filter(['hotel' => $hotelId])) }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('View Currency') }}
            </a>
            <a href="{{ route('admin.organization') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Dashboard') }}
            </a>
        </div>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <form wire:submit="save" class="max-w-3xl space-y-4">
            <flux:select wire:model.live="hotelId" :label="__('Organization')" :placeholder="__('Choose an organization...')" required>
                @foreach ($this->hotels as $hotel)
                    <flux:select.option value="{{ $hotel->id }}">{{ $hotel->name }} ({{ $hotel->currency }})</flux:select.option>
                @endforeach
            </flux:select>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model.blur="code" :label="__('Code')" :placeholder="__('e.g. USD')" maxlength="3" class="uppercase" required />
                <flux:input wire:model="name" :label="__('Name')" :placeholder="__('e.g. US Dollar')" required />
                <flux:input wire:model="symbol" :label="__('Symbol')" :placeholder="__('e.g. $')" />
            </div>

            <flux:input wire:model="exchangeRate" type="number" step="any" min="0" required
                :label="__('Exchange rate')"
                :description="$this->selectedHotel
                    ? __('How many :base one unit of this currency is worth.', ['base' => $this->selectedHotel->currency])
                    : __('How many units of the organization\'s base currency one unit of this currency is worth.')" />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="check">{{ __('Add Currency') }}</flux:button>
            </div>
        </form>
    </div>
</section>
