<nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
    <a href="{{ route('expense-categories.create') }}" wire:navigate
        class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Add Expenses Category') }}</a>
    <span class="text-zinc-400 dark:text-zinc-600">|</span>
    <a href="{{ route('expense-categories.index') }}" wire:navigate
        class="text-sky-600 hover:underline dark:text-sky-400">{{ __('View Expenses Category') }}</a>
    <span class="text-zinc-400 dark:text-zinc-600">|</span>
    <a href="{{ route('expenses.create') }}" wire:navigate
        class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Add Daily Expenses') }}</a>
    <span class="text-zinc-400 dark:text-zinc-600">|</span>
    <a href="{{ route('expenses.list') }}" wire:navigate
        class="text-sky-600 hover:underline dark:text-sky-400">{{ __('View Daily Expenses') }}</a>
    @can('view-reports')
        <span class="text-zinc-400 dark:text-zinc-600">|</span>
        <a href="{{ route('reports.expenses') }}" wire:navigate
            class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Expenses Report') }}</a>
    @endcan
</nav>
