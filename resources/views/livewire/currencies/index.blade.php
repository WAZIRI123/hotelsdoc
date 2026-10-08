<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.currency-dollar class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Currencies') }}</flux:heading>

            <button type="button" wire:click="openCreate"
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Add currency') }}
            </button>
        </div>

        <flux:text class="mt-3 text-sm">
            {{ __('Prices and bills are in :local. Payments can also be taken in any active currency below; it is converted to :local at its rate to check it covers what is due.', ['local' => $this->localCurrency]) }}
        </flux:text>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Currency') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Rate') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Last updated') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                <flux:table.row>
                    <flux:table.cell variant="strong">{{ $this->localCurrency }}</flux:table.cell>
                    <flux:table.cell align="end" class="font-mono">1</flux:table.cell>
                    <flux:table.cell><flux:badge color="sky" size="sm">{{ __('Local currency') }}</flux:badge></flux:table.cell>
                    <flux:table.cell class="text-zinc-500">—</flux:table.cell>
                    <flux:table.cell></flux:table.cell>
                </flux:table.row>

                @forelse ($this->currencies as $currency)
                    <flux:table.row :key="$currency->id">
                        <flux:table.cell variant="strong">{{ $currency->code }}</flux:table.cell>
                        <flux:table.cell align="end" class="font-mono">
                            1 {{ $currency->code }} = {{ $this->localCurrency }} {{ number_format((float) $currency->rate, 2) }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($currency->is_active)
                                <flux:badge color="green" size="sm">{{ __('Accepted') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-sm text-zinc-500">
                            {{ $currency->updated_at?->format('d M Y H:i') }}
                            @if ($currency->updatedBy)
                                · {{ $currency->updatedBy->name }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $currency->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger"
                                        wire:click="delete({{ $currency->id }})"
                                        wire:confirm="{{ __('Delete this currency?') }}">
                                        {{ __('Delete') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-500">
                            {{ __('No other currencies yet. Add one, e.g. USD, with how much :local one unit is worth.', ['local' => $this->localCurrency]) }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal name="currency-form" class="md:w-[480px]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">
                {{ $editingId ? __('Edit currency') : __('Add currency') }}
            </flux:heading>

            <flux:input wire:model="code" :label="__('Currency code')" maxlength="3" placeholder="USD"
                :description="__('3-letter ISO code, e.g. USD, EUR, KES.')" required autofocus />
            <flux:input type="number" min="0" step="any" wire:model="rate"
                :label="__('Rate (:local for 1 unit)', ['local' => $this->localCurrency])"
                :description="__('Changing the rate only affects payments taken from now on.')" required />
            <flux:switch wire:model="is_active" :label="__('Accept payments in this currency')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
