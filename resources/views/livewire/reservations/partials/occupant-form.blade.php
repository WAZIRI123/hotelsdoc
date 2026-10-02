<form wire:submit="saveOccupant" class="space-y-3 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
    <flux:heading size="sm">{{ $naming ? __('Who is staying in this bed?') : __('Add a person to this room') }}</flux:heading>

    @unless ($naming)
        <flux:radio.group wire:model="occupantAgeCategory" variant="segmented" size="sm">
            <flux:radio value="adult" :label="__('Adult')" />
            <flux:radio value="child" :label="__('Child')" />
        </flux:radio.group>
        <flux:text class="text-xs">{{ __('Pick a guest now, or leave it empty and name them at check-in.') }}</flux:text>
    @endunless

    <livewire:reservations.guest-picker :key="'occupant-picker-'.$pickerKey" />
    @error('occupantGuestId') <flux:text class="text-sm text-red-600">{{ $message }}</flux:text> @enderror

    <div class="flex justify-end gap-2">
        <flux:button size="sm" variant="ghost" wire:click="cancelOccupantForm">{{ __('Cancel') }}</flux:button>
        <flux:button size="sm" type="submit" variant="primary">{{ $naming ? __('Save name') : __('Add person') }}</flux:button>
    </div>
</form>
