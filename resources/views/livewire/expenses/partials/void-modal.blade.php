<flux:modal name="void-expense" class="md:w-[480px]">
    <form wire:submit="voidExpense" class="space-y-6">
        <flux:heading size="lg">{{ __('Void expense') }}</flux:heading>
        <flux:text>{{ __('Use this for an expense recorded by mistake. It stays on record, marked voided, but is removed from the expenses report, cash-up and profit & loss.') }}</flux:text>
        <flux:input wire:model="voidReason" :label="__('Reason')" required />
        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Void expense') }}</flux:button>
        </div>
    </form>
</flux:modal>
