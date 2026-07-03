<section class="w-full">
    <div class="flex items-center justify-between gap-4 pb-6">
        <div>
            <flux:heading size="xl">{{ __('Rate plans') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Sellable products: room type × meal plan × price. Open a plan\'s calendar to set per-date overrides.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            {{ __('New rate plan') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Room type') }}</flux:table.column>
            <flux:table.column>{{ __('Meal plan') }}</flux:table.column>
            <flux:table.column>{{ __('Base rate') }}</flux:table.column>
            <flux:table.column>{{ __('Extra adult / child') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->ratePlans as $plan)
                <flux:table.row :key="$plan->id">
                    <flux:table.cell variant="strong">{{ $plan->name }}</flux:table.cell>
                    <flux:table.cell>{{ $plan->roomType?->name }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($plan->mealPlan)
                            <flux:badge color="amber" size="sm">{{ $plan->mealPlan->code }}</flux:badge>
                        @else
                            <flux:text class="text-zinc-400">—</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $plan->price->format() }}</flux:table.cell>
                    <flux:table.cell class="text-sm text-zinc-500">
                        {{ $plan->extra_adult_price->format() }} / {{ $plan->extra_child_price->format() }}
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
                                <flux:menu.item icon="calendar-days" :href="route('rate-plans.calendar', $plan)" wire:navigate>
                                    {{ __('Open calendar') }}
                                </flux:menu.item>
                                <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $plan->id }})">
                                    {{ __('Edit') }}
                                </flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger"
                                    wire:click="delete({{ $plan->id }})"
                                    wire:confirm="{{ __('Delete this rate plan?') }}">
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="text-center text-zinc-500">
                        {{ __('No rate plans yet. Create one to expose seasonal / meal-plan pricing.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="rate-plan-form" class="md:w-[560px]">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    {{ $editingId ? __('Edit rate plan') : __('New rate plan') }}
                </flux:heading>
                <flux:text class="mt-2">
                    {{ __('A rate plan combines a room type with a meal plan and a base price.') }}
                </flux:text>
            </div>

            <flux:input wire:model="name" :label="__('Name')" placeholder="Deluxe — Half Board" required autofocus />

            <div class="grid grid-cols-2 gap-4">
                <flux:select wire:model="room_type_id" :label="__('Room type')" required>
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach ($this->roomTypes as $type)
                        <flux:select.option :value="$type->id">{{ $type->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="meal_plan_id" :label="__('Meal plan')">
                    <flux:select.option value="">{{ __('None (room only)') }}</flux:select.option>
                    @foreach ($this->mealPlans as $mp)
                        <flux:select.option :value="$mp->id">{{ $mp->code }} — {{ $mp->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input wire:model.number="price_tsh" type="number" min="0"
                :label="__('Base rate (TSh per night)')" required />

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model.number="extra_adult_price_tsh" type="number" min="0"
                    :label="__('Extra adult (TSh)')" />
                <flux:input wire:model.number="extra_child_price_tsh" type="number" min="0"
                    :label="__('Extra child (TSh)')" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="valid_from" type="date" :label="__('Valid from')" />
                <flux:input wire:model="valid_to" type="date" :label="__('Valid to')" />
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
