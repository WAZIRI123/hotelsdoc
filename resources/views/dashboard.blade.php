<x-layouts::app :title="__('Dashboard')">
    @php
        $user = auth()->user();
        $primaryRole = $user->getRoleNames()->first() ?? 'staff';
        $roleTitle = ucwords(str_replace('_', ' ', $primaryRole));

        $cards = [
            [
                'permission' => 'manage-guests',
                'route' => 'guests.index',
                'label' => __('Guest Book'),
                'icon' => 'book-open',
                'color' => 'bg-green-600',
            ],
            [
                'permission' => 'manage-reservations',
                'route' => 'reservations.index',
                'label' => __('Reservations'),
                'icon' => 'calendar-days',
                'color' => 'bg-sky-500',
            ],
            [
                'permission' => 'manage-rooms',
                'route' => 'rooms.index',
                'label' => __('Rooms'),
                'icon' => 'building-office-2',
                'color' => 'bg-indigo-500',
            ],
            [
                'permission' => 'manage-room-types',
                'route' => 'room-types.index',
                'label' => __('Room types'),
                'icon' => 'rectangle-stack',
                'color' => 'bg-teal-500',
            ],
            [
                'permission' => 'manage-rooms',
                'route' => 'meal-plans.index',
                'label' => __('Meal plans'),
                'icon' => 'cake',
                'color' => 'bg-amber-500',
            ],
            [
                'permission' => 'manage-rooms',
                'route' => 'rate-plans.index',
                'label' => __('Rate plans'),
                'icon' => 'tag',
                'color' => 'bg-purple-500',
            ],
            [
                'permission' => 'operate-pos',
                'route' => 'pos.lobby',
                'label' => __('POS terminal'),
                'icon' => 'computer-desktop',
                'color' => 'bg-red-500',
            ],
            [
                'permission' => 'manage-outlets',
                'route' => 'outlets.index',
                'label' => __('Outlets & menus'),
                'icon' => 'building-storefront',
                'color' => 'bg-orange-500',
            ],
            [
                'permission' => 'view-reports',
                'route' => 'reports.cash-up',
                'label' => __('Get Reports'),
                'icon' => 'chart-bar',
                'color' => 'bg-blue-500',
            ],
            [
                'permission' => 'manage-users',
                'route' => 'users.index',
                'label' => __('Roles & permissions'),
                'icon' => 'key',
                'color' => 'bg-rose-400',
            ],
        ];
    @endphp

    <section class="w-full">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center gap-3">
                <flux:icon.information-circle class="size-7 text-zinc-900 dark:text-zinc-100" />
                <flux:heading size="xl" class="!text-3xl !font-normal">
                    {{ $roleTitle }} {{ __('Dashboard') }}
                </flux:heading>
            </div>

            <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($cards as $card)
                    @can($card['permission'])
                        <a href="{{ route($card['route']) }}" wire:navigate
                            class="{{ $card['color'] }} flex items-center justify-center gap-4 rounded-lg border-2 border-white/25 px-6 py-6 text-white shadow-md transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-white/60 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                            <flux:icon :name="$card['icon']" class="size-8 flex-shrink-0" />
                            <span class="text-lg font-medium">{{ $card['label'] }}</span>
                        </a>
                    @endcan
                @endforeach
            </div>
        </div>
    </section>
</x-layouts::app>
