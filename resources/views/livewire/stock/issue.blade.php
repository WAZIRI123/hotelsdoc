<section class="w-full space-y-6">
    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap items-center gap-3">
            <flux:icon.arrow-up-tray class="size-7 text-zinc-900 dark:text-zinc-100" />
            <flux:heading size="xl" class="!text-3xl !font-normal">{{ __('Issue Products') }} — {{ $outlet->name }}</flux:heading>

            @can('view-reports')
                <a href="{{ route('reports.stock', ['outlet' => $outlet->id, 'view' => 'issued']) }}" wire:navigate
                    class="inline-flex items-center rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                    {{ __('Issued Stock') }}
                </a>
            @endcan
            <a href="{{ route('manage-stock.outlet', $outlet) }}" wire:navigate
                class="inline-flex items-center rounded-md bg-sky-400 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900">
                {{ __('Back to Manage Stock') }}
            </a>
        </div>

        <nav class="mt-4 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
            <a href="{{ route('menu.index', $outlet) }}" wire:navigate
                class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Products') }}</a>
            @can('view-reports')
                <span class="text-zinc-400 dark:text-zinc-600">|</span>
                <a href="{{ route('reports.stock', ['outlet' => $outlet->id]) }}" wire:navigate
                    class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Receive Stock') }}</a>
                <span class="text-zinc-400 dark:text-zinc-600">|</span>
                <a href="{{ route('reports.stock', ['outlet' => $outlet->id, 'view' => 'on-hand']) }}" wire:navigate
                    class="text-sky-600 hover:underline dark:text-sky-400">{{ __('Stock on Hand') }}</a>
            @endcan
        </nav>

        <hr class="my-5 border-zinc-200 dark:border-zinc-700" />

        @if ($this->items->isEmpty())
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('No stock-tracked products in :outlet', ['outlet' => $outlet->name]) }}</flux:callout.heading>
                <flux:callout.text>{{ __('Turn on "Track stock" for a product on the outlet menu, or record stock received for it, before issuing.') }}</flux:callout.text>
            </flux:callout>
        @else
            <form wire:submit="save" class="space-y-6">
                <div class="grid gap-x-10 gap-y-4 md:grid-cols-2">
                    <div class="space-y-4">
                        <flux:select wire:model.live="itemId" :label="__('Product')" required>
                            <flux:select.option value="">{{ __('Choose a product') }}</flux:select.option>
                            @foreach ($this->items as $item)
                                <flux:select.option :value="$item->id" wire:key="issue-item-{{ $item->id }}">
                                    {{ $item->name }} ({{ __(':count in stock', ['count' => $item->stock_quantity]) }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input type="number" min="1" step="1" wire:model="quantity" :label="__('Quantity')" required
                            :description="$this->selectedItem ? __(':count in stock.', ['count' => $this->selectedItem->stock_quantity]) : null" />

                        <flux:input type="date" wire:model="issueDate" :label="__('Date')" required />
                    </div>

                    <div class="space-y-4">
                        <flux:select wire:model.live="reason" :label="__('Reason')" required>
                            @foreach (\App\Models\StockIssue::REASONS as $value => $label)
                                <flux:select.option :value="$value">{{ __($label) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        @if ($reason === \App\Models\StockIssue::REASON_TRANSFER)
                            <flux:select wire:model="toItemId" :label="__('Receiving product')" required
                                :description="__('The quantity is added to this product\'s stock on hand.')">
                                <flux:select.option value="">{{ __('Choose a product') }}</flux:select.option>
                                @foreach ($this->transferTargets as $target)
                                    <flux:select.option :value="$target->id" wire:key="issue-target-{{ $target->id }}">
                                        {{ $target->name }} · {{ $target->outlet->name }}
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                        @else
                            <flux:input wire:model="issuedTo" :label="__('Issued to')"
                                :placeholder="$reason === \App\Models\StockIssue::REASON_WASTAGE ? __('e.g. Expired, broken') : __('e.g. Kitchen, Housekeeping, staff name')" />
                        @endif

                        <flux:input wire:model="description" :label="__('Description')" :placeholder="__('e.g. Requisition number')" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check">{{ __('Issue Stock') }}</flux:button>
                </div>
            </form>
        @endif
    </div>

    <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:heading size="lg">{{ __('Recent issues from :outlet', ['outlet' => $outlet->name]) }}</flux:heading>

        <flux:table class="mt-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Product') }}</flux:table.column>
                <flux:table.column>{{ __('Reason') }}</flux:table.column>
                <flux:table.column>{{ __('Issued to') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Quantity') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->recentIssues as $issue)
                    <flux:table.row :key="$issue->id">
                        <flux:table.cell>{{ $issue->issue_date->format('d-m-Y') }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $issue->menuItem->name }}</flux:table.cell>
                        <flux:table.cell>{{ $issue->reasonLabel() }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($issue->toMenuItem)
                                {{ $issue->toMenuItem->name }} · {{ $issue->toMenuItem->outlet->name }}
                            @else
                                {{ $issue->issued_to ?? '—' }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end" class="tabular-nums">{{ $issue->quantity }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            <button type="button" wire:click="reverse({{ $issue->id }})"
                                wire:confirm="{{ __('Reverse this issue? The quantity goes back into stock.') }}"
                                class="text-sm text-red-600 hover:underline dark:text-red-400">{{ __('Reverse') }}</button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center text-zinc-500">{{ __('Nothing issued from this outlet yet.') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
</section>
