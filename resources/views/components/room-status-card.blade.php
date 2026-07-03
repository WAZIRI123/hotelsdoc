@props([
    'class' => 'bg-zinc-500',
    'label' => '',
])

<div {{ $attributes->class([$class, 'flex aspect-[3/2] w-full flex-col items-center justify-center rounded-lg border-2 border-white p-4 text-white shadow ring-1 ring-zinc-200 dark:ring-zinc-700']) }}>
    <svg viewBox="0 0 64 40" fill="currentColor" class="h-16 w-auto" aria-hidden="true">
        <circle cx="14" cy="14" r="6" />
        <path d="M22 22 H56 a2 2 0 0 1 2 2 v6 H6 v-4 a4 4 0 0 1 4 -4 z" />
        <rect x="4" y="30" width="56" height="3" rx="1" />
        <rect x="6" y="33" width="3" height="4" />
        <rect x="55" y="33" width="3" height="4" />
    </svg>
    <span class="mt-2 text-base font-semibold tracking-wider">{{ $label }}</span>
</div>
