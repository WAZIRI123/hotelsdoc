<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.cube class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Add Extras Services') }}</flux:heading>

            <a href="{{ route('extras.orders') }}" wire:navigate
                class="inline-flex items-center gap-2 rounded-md bg-amber-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                <flux:icon.list-bullet class="size-4" />
                {{ __('Extra Orders') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('extras.orders') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Extra orders') }}</a>
            @can('manage-hotel')
                <span class="text-zinc-400 dark:text-zinc-600">|</span>
                <a href="{{ route('extras.services') }}" wire:navigate
                    class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Manage extra services & prices') }}</a>
            @endcan
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('guests.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Guest Book') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-6 md:grid-cols-3">
                <flux:select wire:model.live="customer" :label="__('Customer:')">
                    <flux:select.option value="">{{ __('Select Customer') }}</flux:select.option>
                    <flux:select.option value="{{ \App\Livewire\Extras\Create::WALK_IN }}">{{ __('Walk-in Customer') }}</flux:select.option>
                    @foreach ($this->inHouseStays as $stay)
                        <flux:select.option value="{{ $stay->id }}">
                            {{ $stay->guest?->fullName() }}
                            — {{ __('Room') }} {{ $stay->reservation?->rooms->first()?->room?->number ?? '—' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="date" wire:model="orderDate" :label="__('Date:')" />
            </div>

            @if ($customer === \App\Livewire\Extras\Create::WALK_IN)
                <div class="grid gap-6 md:grid-cols-3">
                    <flux:select wire:model.live="paymentMethod" :label="__('Paid by:')">
                        <flux:select.option value="">{{ __('Select payment method') }}</flux:select.option>
                        @foreach ($this->paymentMethods as $method)
                            <flux:select.option :value="$method->code">{{ $method->label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="paymentReference"
                        :label="$this->paymentRequiresReference ? __('Reference / transaction ID:') : __('Reference (optional):')" />
                </div>
            @endif

            <div>
                <button type="submit"
                    class="inline-flex items-center rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                    <span wire:loading.remove wire:target="save">{{ __('Save Record') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                </button>
            </div>

            @if ($this->services->isEmpty())
                <div class="rounded-lg border border-dashed border-zinc-200 p-6 text-center text-sm text-zinc-500 dark:border-zinc-700">
                    {{ __('No extra services yet.') }}
                    @can('manage-hotel')
                        <flux:link :href="route('extras.services')" wire:navigate>{{ __('Create some and set their prices.') }}</flux:link>
                    @else
                        {{ __('Ask an administrator to set them up.') }}
                    @endcan
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-b-2 border-zinc-200 text-left dark:border-zinc-700">
                            <th class="w-12 border border-zinc-200 p-3 font-semibold dark:border-zinc-700">{{ __('No') }}</th>
                            <th class="border border-zinc-200 p-3 font-semibold dark:border-zinc-700">{{ __('Extra item') }}</th>
                            <th class="border border-zinc-200 p-3 font-semibold dark:border-zinc-700">{{ __('Quantity') }}</th>
                            <th class="border border-zinc-200 p-3 font-semibold dark:border-zinc-700">{{ __('Price (:currency)', ['currency' => \App\Support\HotelCurrency::current()]) }}</th>
                            <th class="w-24 border border-zinc-200 p-3 dark:border-zinc-700">
                                <button type="button" wire:click="addLine" title="{{ __('Add row') }}"
                                    class="inline-flex items-center rounded-md bg-sky-600 px-4 py-2 text-white shadow-sm transition hover:bg-sky-700">
                                    +
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $index => $line)
                            <tr wire:key="extra-line-{{ $index }}" class="align-top">
                                <td class="border border-zinc-200 p-3 dark:border-zinc-700">{{ $index + 1 }}</td>
                                <td class="border border-zinc-200 p-3 dark:border-zinc-700">
                                    <flux:select wire:model.live="lines.{{ $index }}.extra_service_id">
                                        <flux:select.option value="">{{ __('Select Extras') }}</flux:select.option>
                                        @foreach ($this->services as $service)
                                            <flux:select.option value="{{ $service->id }}">{{ $service->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="lines.{{ $index }}.extra_service_id" />
                                </td>
                                <td class="border border-zinc-200 p-3 dark:border-zinc-700">
                                    <flux:input type="number" min="1" step="1" wire:model="lines.{{ $index }}.quantity" />
                                    <flux:error name="lines.{{ $index }}.quantity" />
                                </td>
                                <td class="border border-zinc-200 p-3 dark:border-zinc-700">
                                    <flux:input type="number" min="0" step="any" wire:model="lines.{{ $index }}.price" />
                                    <flux:error name="lines.{{ $index }}.price" />
                                </td>
                                <td class="border border-zinc-200 p-3 dark:border-zinc-700">
                                    <button type="button" wire:click="removeLine({{ $index }})"
                                        class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</section>
