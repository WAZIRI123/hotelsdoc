<section class="w-full">
    <div class="flex items-center justify-between gap-4 pb-6">
        <div>
            <flux:heading size="xl">{{ __('Meal plans') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Board basis codes attached to rate plans (EP, BB, HB, FB, AI).') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            {{ __('New meal plan') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Code') }}</flux:table.column>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Meals included') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->mealPlans as $plan)
                <flux:table.row :key="$plan->id">
                    <flux:table.cell class="font-mono text-sm">{{ $plan->code }}</flux:table.cell>
                    <flux:table.cell variant="strong">{{ $plan->name }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($plan->mealsIncludedCount() === 0)
                            <flux:text class="text-zinc-500">{{ __('Room only') }}</flux:text>
                        @else
                            <div class="flex gap-1">
                                @if ($plan->includes_breakfast)
                                    <flux:badge color="amber" size="sm">{{ __('Breakfast') }}</flux:badge>
                                @endif
                                @if ($plan->includes_lunch)
                                    <flux:badge color="orange" size="sm">{{ __('Lunch') }}</flux:badge>
                                @endif
                                @if ($plan->includes_dinner)
                                    <flux:badge color="rose" size="sm">{{ __('Dinner') }}</flux:badge>
                                @endif
                            </div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($plan->is_active)
                            <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="text-right">
                        <flux:dropdown align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $plan->id }})">
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger"
                                    wire:click="delete({{ $plan->id }})"
                                    wire:confirm="{{ __('Delete this meal plan?') }}">
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">
                        {{ __('No meal plans yet. Add EP (room only) as a starting point.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="meal-plan-form" class="md:w-[480px]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? __('Edit meal plan') : __('New meal plan') }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ __('Industry codes: EP (room only), BB, HB, FB, AI. You can add custom ones.') }}
                </flux:text>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="code" :label="__('Code')" required autofocus />
                <flux:input wire:model="name" :label="__('Name')" required />
            </div>

            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />

            <div class="space-y-2">
                <flux:heading size="sm">{{ __('Meals included') }}</flux:heading>
                <flux:checkbox wire:model="includes_breakfast" :label="__('Breakfast')" />
                <flux:checkbox wire:model="includes_lunch" :label="__('Lunch')" />
                <flux:checkbox wire:model="includes_dinner" :label="__('Dinner')" />
            </div>

            <flux:switch wire:model="is_active" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
