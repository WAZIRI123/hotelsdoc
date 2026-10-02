<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.information-circle class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('View Currency') }}</flux:heading>

            <flux:spacer />

            <a href="{{ route('admin.currencies.create', array_filter(['hotel' => $hotelId])) }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Add Currency') }}
            </a>
            <a href="{{ route('admin.organization') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Dashboard') }}
            </a>
        </div>

        <div class="mt-4 max-w-sm">
            <flux:select wire:model.live="hotelId" :placeholder="__('All organizations')">
                <flux:select.option value="">{{ __('All organizations') }}</flux:select.option>
                @foreach ($this->hotels as $hotel)
                    <flux:select.option value="{{ $hotel->id }}">{{ $hotel->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="text-zinc-800 dark:text-zinc-100">
                        <th class="border border-zinc-200 px-4 py-3 text-start font-semibold dark:border-zinc-700">{{ __('Organization') }}</th>
                        <th class="border border-zinc-200 px-4 py-3 text-start font-semibold dark:border-zinc-700">{{ __('Code') }}</th>
                        <th class="border border-zinc-200 px-4 py-3 text-start font-semibold dark:border-zinc-700">{{ __('Name') }}</th>
                        <th class="border border-zinc-200 px-4 py-3 text-start font-semibold dark:border-zinc-700">{{ __('Symbol') }}</th>
                        <th class="border border-zinc-200 px-4 py-3 text-end font-semibold dark:border-zinc-700">{{ __('Exchange rate') }}</th>
                        <th class="border border-zinc-200 px-4 py-3 text-start font-semibold dark:border-zinc-700">{{ __('Added by') }}</th>
                        <th class="border border-zinc-200 px-4 py-3 text-center font-semibold dark:border-zinc-700">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->currencies as $currency)
                        <tr wire:key="currency-{{ $currency->id }}" class="text-zinc-700 even:bg-zinc-50 dark:text-zinc-200 dark:even:bg-zinc-800/60">
                            <td class="border border-zinc-200 px-4 py-2 dark:border-zinc-700">{{ $currency->hotel->name }}</td>
                            <td class="border border-zinc-200 px-4 py-2 font-medium dark:border-zinc-700">{{ $currency->code }}</td>
                            <td class="border border-zinc-200 px-4 py-2 dark:border-zinc-700">{{ $currency->name }}</td>
                            <td class="border border-zinc-200 px-4 py-2 dark:border-zinc-700">{{ $currency->symbol }}</td>
                            <td class="border border-zinc-200 px-4 py-2 text-end tabular-nums dark:border-zinc-700">
                                1 {{ $currency->code }} = {{ rtrim(rtrim(number_format((float) $currency->exchange_rate, 6), '0'), '.') }} {{ $currency->hotel->currency }}
                            </td>
                            <td class="border border-zinc-200 px-4 py-2 dark:border-zinc-700">
                                {{ $currency->creator?->name }} / {{ $currency->created_at?->format('Y-m-d') }}
                            </td>
                            <td class="border border-zinc-200 px-4 py-2 text-center dark:border-zinc-700">
                                <button type="button" wire:click="delete({{ $currency->id }})"
                                    wire:confirm="{{ __('Remove :code from :hotel?', ['code' => $currency->code, 'hotel' => $currency->hotel->name]) }}"
                                    class="rounded-md bg-red-600 px-3 py-1 text-white shadow-sm transition hover:bg-red-700">
                                    {{ __('Delete') }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="border border-zinc-200 px-4 py-6 text-center text-zinc-500 dark:border-zinc-700">
                                {{ __('No currencies added yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
