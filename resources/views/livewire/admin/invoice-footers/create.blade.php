<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.information-circle class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Add Invoice Footer') }}</flux:heading>

            <flux:spacer />

            <a href="{{ route('admin.invoice-footers.index') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('View Invoice Footer') }}
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
                    <flux:select.option value="{{ $hotel->id }}">{{ $hotel->name }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($this->existingFooter)
                <flux:callout icon="information-circle" color="sky">
                    <flux:callout.text>{{ __('This organization already has a footer. Saving will replace it.') }}</flux:callout.text>
                </flux:callout>
            @endif

            <flux:textarea wire:model="content" rows="6" required
                :label="__('Footer text')"
                :description="__('Printed at the bottom of every bill, e.g. bank details, payment terms or a thank-you note. Line breaks are kept.')" />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="check">
                    {{ $this->existingFooter ? __('Update Invoice Footer') : __('Add Invoice Footer') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>
