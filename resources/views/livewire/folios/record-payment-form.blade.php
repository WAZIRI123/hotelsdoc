<div>
    <flux:modal name="record-payment" class="md:w-[480px]">
        <form wire:submit="submit" class="space-y-6">
            <flux:heading size="lg">{{ __('Record payment') }}</flux:heading>

            <flux:select wire:model.live="method_code" :label="__('Payment method')" required>
                <flux:select.option value="">{{ __('Choose method…') }}</flux:select.option>
                @foreach ($this->methods as $method)
                    <flux:select.option :value="$method->code">{{ $method->label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model.number="amount_tsh" type="number" min="1"
                :label="__('Amount (TSh)')" required />

            @if ($this->selectedRequiresReference)
                <flux:input wire:model="reference"
                    :label="__('Reference / transaction ID')" required />
                <flux:input wire:model="payer_phone"
                    :label="__('Payer phone (optional)')" />
            @else
                <flux:input wire:model="reference"
                    :label="__('Reference (optional)')" />
            @endif

            <flux:textarea wire:model="note" :label="__('Note (optional)')" rows="2" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Record') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
