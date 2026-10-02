<section class="w-full">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.information-circle class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('View Invoice Footer') }}</flux:heading>

            <flux:spacer />

            <a href="{{ route('admin.invoice-footers.create') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Add Invoice Footer') }}
            </a>
            <a href="{{ route('admin.organization') }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Dashboard') }}
            </a>
        </div>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="text-zinc-800 dark:text-zinc-100">
                        <th class="border border-zinc-200 px-4 py-3 text-start font-semibold dark:border-zinc-700">{{ __('Organization') }}</th>
                        <th class="border border-zinc-200 px-4 py-3 text-start font-semibold dark:border-zinc-700">{{ __('Footer') }}</th>
                        <th class="border border-zinc-200 px-4 py-3 text-start font-semibold dark:border-zinc-700">{{ __('Approved-by') }}</th>
                        <th class="border border-zinc-200 px-4 py-3 text-start font-semibold dark:border-zinc-700">{{ __('Edited') }}</th>
                        <th class="border border-zinc-200 px-4 py-3 text-center font-semibold dark:border-zinc-700">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->footers as $footer)
                        <tr wire:key="footer-{{ $footer->id }}" class="align-top text-zinc-700 even:bg-zinc-50 dark:text-zinc-200 dark:even:bg-zinc-800/60">
                            <td class="border border-zinc-200 px-4 py-2 font-medium dark:border-zinc-700">{{ $footer->hotel->name }}</td>
                            <td class="border border-zinc-200 px-4 py-2 whitespace-pre-line dark:border-zinc-700">{{ $footer->content }}</td>
                            <td class="border border-zinc-200 px-4 py-2 whitespace-nowrap dark:border-zinc-700">
                                {{ $footer->creator?->name }} / {{ $footer->created_at?->format('Y-m-d') }}
                            </td>
                            <td class="border border-zinc-200 px-4 py-2 whitespace-nowrap dark:border-zinc-700">
                                {{ $footer->editor?->name }} / {{ $footer->edited_by ? $footer->updated_at?->format('Y-m-d') : '' }}
                            </td>
                            <td class="border border-zinc-200 px-4 py-2 dark:border-zinc-700">
                                <div class="flex flex-col items-center gap-2">
                                    <a href="{{ route('admin.invoice-footers.create', ['hotel' => $footer->hotel_id]) }}" wire:navigate
                                        class="rounded-md bg-amber-500 px-3 py-1 text-white shadow-sm transition hover:bg-amber-600">
                                        {{ __('Edit') }}
                                    </a>
                                    <button type="button" wire:click="delete({{ $footer->id }})"
                                        wire:confirm="{{ __('Remove the invoice footer of :hotel?', ['hotel' => $footer->hotel->name]) }}"
                                        class="rounded-md bg-red-600 px-3 py-1 text-white shadow-sm transition hover:bg-red-700">
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="border border-zinc-200 px-4 py-6 text-center text-zinc-500 dark:border-zinc-700">
                                {{ __('No invoice footers added yet.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
