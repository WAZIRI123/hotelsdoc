<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.briefcase class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">
                {{ $agent ? __('Edit Agent') : __('Register Agent') }}
            </flux:heading>

            <a href="{{ route('agents.index') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('View agents') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('agents.index') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('All Agents') }}</a>
            <span class="text-zinc-400 dark:text-zinc-600">|</span>
            <a href="{{ route('reservations.create') }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('New Reservation') }}</a>
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-x-10 gap-y-4 md:grid-cols-2">
                <div class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Agent Name') }} <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="name" :placeholder="__('Agent\'s complete name')" required autofocus />
                        <flux:error name="name" />
                    </flux:field>
                    <flux:input wire:model="phone" :label="__('Phone')" :placeholder="__('Agent\'s phone')" />
                    <flux:input wire:model="email" type="email" :label="__('Email')" :placeholder="__('Agent\'s email')" />
                    <flux:input wire:model="tin" :label="__('TIN')" :placeholder="__('Agent\'s TIN number')" />
                </div>

                <div class="space-y-4">
                    <flux:input wire:model="vrn" :label="__('VRN')" :placeholder="__('Agent\'s VRN number')" />
                    <flux:input wire:model="address" :label="__('Address')" :placeholder="__('Agent\'s address')" />

                    <flux:field>
                        <flux:label>{{ __('Qualification') }}</flux:label>

                        @if ($addingQualification)
                            <div class="flex items-start gap-2">
                                <div class="flex-1">
                                    <flux:input wire:model="newQualification"
                                        wire:keydown.enter.prevent="addQualification"
                                        :placeholder="__('New qualification, e.g. Special Agent')" autofocus />
                                </div>
                                <flux:button type="button" variant="primary" icon="plus" wire:click="addQualification">
                                    {{ __('Add') }}
                                </flux:button>
                                <flux:button type="button" variant="ghost" wire:click="cancelAddingQualification">
                                    {{ __('Cancel') }}
                                </flux:button>
                            </div>
                            <flux:error name="newQualification" />
                        @else
                            <flux:select wire:model.live="agent_qualification_id">
                                <flux:select.option value="">{{ __('Select Qualification') }}</flux:select.option>
                                @foreach ($this->qualifications as $qualification)
                                    <flux:select.option :value="(string) $qualification->id" wire:key="qualification-{{ $qualification->id }}">
                                        {{ $qualification->name }}
                                    </flux:select.option>
                                @endforeach
                                <flux:select.option :value="\App\Livewire\Agents\Register::NEW_QUALIFICATION">
                                    {{ __('+ Add new qualification…') }}
                                </flux:select.option>
                            </flux:select>
                            <flux:error name="agent_qualification_id" />
                        @endif
                    </flux:field>

                    @if ($agent)
                        <flux:switch wire:model="is_active" :label="__('Active')" />
                    @endif
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" :href="route('agents.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button type="submit" variant="primary" icon="check">
                    {{ $agent ? __('Save Agent') : __('Register Agent') }}
                </flux:button>
            </div>
        </form>
    </div>
</section>
