<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Users')" :subheading="__('Manage who can sign in to this hotel and what they can do.')">
        <div class="flex items-center justify-end pb-4">
            <flux:button variant="primary" icon="plus" wire:click="openCreate">
                {{ __('Add user') }}
            </flux:button>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell variant="strong">{{ $user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $user->email }}</flux:table.cell>
                        <flux:table.cell>
                            @php($roleName = $user->roles->first()?->name)
                            @if ($roleName)
                                <flux:badge color="zinc" size="sm">{{ str_replace('_', ' ', $roleName) }}</flux:badge>
                            @else
                                <flux:badge color="amber" size="sm">{{ __('No role') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            <flux:dropdown align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $user->id }})">
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="user-minus" variant="danger"
                                        wire:click="removeFromHotel({{ $user->id }})"
                                        wire:confirm="{{ __('Remove this user from the hotel?') }}">
                                        {{ __('Remove') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-500">
                            {{ __('No users yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <flux:modal name="user-form" class="md:w-[480px]">
            <form wire:submit="save" class="space-y-6">
                <div>
                    <flux:heading size="lg">
                        {{ $editingUserId ? __('Edit user') : __('Add user') }}
                    </flux:heading>
                    <flux:text class="mt-2">
                        {{ __('Choose a role to set this user\'s permissions inside this hotel.') }}
                    </flux:text>
                </div>

                <flux:input wire:model="name" :label="__('Name')" required autofocus />
                <flux:input wire:model="email" :label="__('Email')" type="email" required />
                <flux:input wire:model="password" :label="$editingUserId ? __('New password (leave blank to keep current)') : __('Password')" type="password" />

                <flux:select wire:model="role" :label="__('Role')">
                    @foreach ($this->availableRoles as $name)
                        <flux:select.option value="{{ $name }}">{{ str_replace('_', ' ', $name) }}</flux:select.option>
                    @endforeach
                </flux:select>

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
