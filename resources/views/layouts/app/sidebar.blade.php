<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-[#F3F3F3] dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="print:hidden border-e border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @php
                $tenantContext = app(\App\Domain\Tenancy\TenantContext::class);
                $currentHotel = $tenantContext->has() ? $tenantContext->current() : null;
                $switchableHotels = app(\App\Domain\Tenancy\HotelAccess::class)->switchableHotels(auth()->user());
                $isVisitingHotel = $currentHotel !== null && ! $switchableHotels->contains($currentHotel);
                $belongsToHotel = $currentHotel !== null
                    || $switchableHotels->isNotEmpty()
                    || (auth()->user()->isSuperAdmin() && session()->has(\App\Domain\Tenancy\HotelAccess::SESSION_KEY));
            @endphp

            @if ($switchableHotels->count() > 1 || ($currentHotel === null && $switchableHotels->isNotEmpty()) || $isVisitingHotel)
                <flux:dropdown position="bottom" align="start" class="mt-2">
                    <button type="button" data-test="hotel-switcher"
                        class="flex w-full items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800">
                        <flux:icon.building-office class="size-4 shrink-0 text-zinc-500" />
                        <span class="flex-1 truncate font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $currentHotel?->name ?? __('Choose a hotel') }}
                        </span>
                        @if ($isVisitingHotel)
                            <flux:badge size="sm" color="amber">{{ __('Visiting') }}</flux:badge>
                        @endif
                        <flux:icon.chevron-up-down class="size-4 shrink-0 text-zinc-400" />
                    </button>

                    <flux:menu class="min-w-60">
                        @if ($switchableHotels->isNotEmpty())
                        <flux:menu.group :heading="__('Switch hotel')">
                            @foreach ($switchableHotels as $switchableHotel)
                                <form method="POST" action="{{ route('hotels.switch', $switchableHotel) }}" wire:key="switch-hotel-{{ $switchableHotel->id }}">
                                    @csrf
                                    <flux:menu.item as="button" type="submit" class="w-full"
                                        :icon="$currentHotel?->is($switchableHotel) ? 'check' : null">
                                        {{ $switchableHotel->name }}
                                    </flux:menu.item>
                                </form>
                            @endforeach
                        </flux:menu.group>
                        @endif

                        @if (auth()->user()->isSuperAdmin())
                            @if ($switchableHotels->isNotEmpty())
                                <flux:menu.separator />
                            @endif
                            <flux:menu.item icon="arrow-uturn-left" :href="route('admin.organization')" wire:navigate>
                                {{ __('Back to platform') }}
                            </flux:menu.item>
                        @endif
                    </flux:menu>
                </flux:dropdown>
            @elseif ($currentHotel !== null)
                <div class="mt-2 flex items-center gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-sm dark:border-zinc-700">
                    <flux:icon.building-office class="size-4 shrink-0 text-zinc-500" />
                    <span class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $currentHotel->name }}</span>
                </div>
            @endif

            <flux:sidebar.nav class="mt-3">
                @if (auth()->user()->isSuperAdmin())
                    <flux:sidebar.item icon="building-office" :href="route('admin.organization')" :current="request()->routeIs('admin.organization', 'admin.hotels.*')" wire:navigate>
                        {{ __('Organization') }}
                    </flux:sidebar.item>
                @endif

                @if ($belongsToHotel)
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                @can('manage-guests')
                    <flux:sidebar.item icon="user-group" :href="route('guests.index')" :current="request()->routeIs('guests.*') || request()->routeIs('reservations.*')" wire:navigate>
                        {{ __('Guest Book') }}
                    </flux:sidebar.item>
                @endcan
                @can('manage-reservations')
                    <flux:sidebar.item icon="briefcase" :href="route('agents.index')" :current="request()->routeIs('agents.*')" wire:navigate>
                        {{ __('Agents') }}
                    </flux:sidebar.item>
                @endcan
                @can('manage-folios')
                    <flux:sidebar.item icon="cube" :href="route('extras.create')" :current="request()->routeIs('extras.*')" wire:navigate>
                        {{ __('Extras services') }}
                    </flux:sidebar.item>
                @endcan

                @can('manage-rooms')
                    <flux:sidebar.item icon="building-office-2" :href="route('room-settings')" :current="request()->routeIs('room-settings', 'rooms.*', 'room-types.*', 'meal-plans.*', 'rate-plans.*')" wire:navigate>
                        {{ __('Manage Rooms') }}
                    </flux:sidebar.item>
                @endcan

                @can('operate-pos')
                    <flux:sidebar.item icon="computer-desktop" :href="route('pos.lobby')" :current="request()->routeIs('pos.*')" wire:navigate>
                        {{ __('Point of Sale') }}
                    </flux:sidebar.item>
                @endcan
                @can('manage-stock')
                    <flux:sidebar.item icon="archive-box" :href="route('manage-stock')" :current="request()->routeIs('manage-stock', 'manage-stock.*', 'outlets.*', 'menu.*', 'suppliers.*', 'stock-types.*')" wire:navigate>
                        {{ __('Manage Stock') }}
                    </flux:sidebar.item>
                @endcan

                <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                    {{ __('Get Reports') }}
                </flux:sidebar.item>

                @can('manage-users')
                    <flux:sidebar.item icon="key" :href="route('users.index')" :current="request()->routeIs('users.index')" wire:navigate>
                        {{ __('Manage Users') }}
                    </flux:sidebar.item>
                @endcan
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            @if ($currentHotel !== null)
                <livewire:layout.sidebar-summary />
            @endif

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden print:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
