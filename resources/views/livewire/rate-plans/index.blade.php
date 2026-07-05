<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.tag class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Rate plans') }}</flux:heading>

            <button type="button" wire:click="openCreate"
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('New rate plan') }}
            </button>

            <a href="{{ route('meal-plans.index') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Meal plans') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('rooms.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Rooms') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('room-types.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Room types') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('meal-plans.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Meal plans') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

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
    </div>

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
