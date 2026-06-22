@props([
    'sidebar' => false,
    'href' => '/',
])

<a href="{{ $href }}" {{ $attributes->class('flex items-center gap-2 px-2 py-1 shrink-0') }}>
    <img
        src="{{ asset('images/logo-bright.png') }}"
        alt="{{ config('app.name', 'Hotels Doc') }}"
        class="block w-40 h-[70px] max-w-none object-contain bg-zinc-900 rounded-md p-2 ring-1 ring-zinc-700 shadow-sm"
    />
    <span class="sr-only">{{ config('app.name', 'Hotels Doc') }}</span>
</a>
