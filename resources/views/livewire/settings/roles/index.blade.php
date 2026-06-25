<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Roles & permissions')" :subheading="__('Adjust what each role can do in this hotel.')">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Permissions') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->roles as $role)
                    <flux:table.row :key="$role->id">
                        <flux:table.cell variant="strong">{{ str_replace('_', ' ', $role->name) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($role->permissions->isEmpty())
                                <flux:text class="text-zinc-500">{{ __('No permissions') }}</flux:text>
                            @else
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($role->permissions as $perm)
                                        <flux:badge color="zinc" size="sm">{{ $perm->name }}</flux:badge>
                                    @endforeach
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $role->id }})">
                                        {{ __('Edit permissions') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="arrow-path"
                                        wire:click="resetToDefaults({{ $role->id }})"
                                        wire:confirm="{{ __('Reset this role to default permissions?') }}">
                                        {{ __('Reset to defaults') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>

        <flux:modal name="role-form" class="md:w-[520px]">
            <form wire:submit="save" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Edit role permissions') }}</flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Tick the permissions this role should have.') }}
                    </flux:text>
                </div>

                <div class="grid gap-2">
                    @foreach ($this->allPermissions as $permission)
                        <flux:checkbox
                            wire:model="selectedPermissions"
                            value="{{ $permission }}"
                            :label="$permission"
                        />
                    @endforeach
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>
                    <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    </x-settings.layout>
</section>
