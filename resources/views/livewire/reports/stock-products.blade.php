@php
    $amount = fn (\App\Support\Money $money): string => number_format($money->toMajor(), 0);
    $minor = fn (?int $minorUnits): string => $minorUnits === null ? '—' : number_format($minorUnits / 100, 0);
    $totals = $this->totals;
    $th = 'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400';
    $td = 'px-4 py-3 whitespace-nowrap';
    $statusBadge = fn (string $status): string => match ($status) {
        'Out of Stock' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        'Low Stock' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        default => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
    };
    $tabs = ['received' => __('Stock Received'), 'issued' => __('Stock Issued'), 'on-hand' => __('Stock on Hand')];
    $canRecord = $this->canRecord();
    $period = ['from' => $this->periodStart()->format('d M Y'), 'to' => $this->periodEnd()->format('d M Y')];
    $issueColor = fn (string $reason): string => match ($reason) {
        \App\Models\StockIssue::REASON_WASTAGE => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
        \App\Models\StockIssue::REASON_TRANSFER => 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-300',
        default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    };
@endphp

<div>
<x-reports.shell
    :heading="__('Stock Products Report')"
    icon="archive-box"
    :subtitle="match ($view) {
        'received' => __('Stock received from :from to :to', $period),
        'issued' => __('Stock issued from :from to :to', $period),
        default => __('Stock on hand now, with stock received, issued and sold from :from to :to', $period),
    }"
    :tiles="match ($view) {
        'received' => [
            [__('Deliveries'), $this->entries->count(), 'text-zinc-900 dark:text-zinc-100'],
            [__('Expiring Soon'), $this->entries->filter(fn ($entry) => $this->expiryState($entry) !== null)->count(), 'text-amber-600 dark:text-amber-400'],
            [__('Quantity Received'), number_format($this->entries->sum('quantity'), 2), 'text-green-600 dark:text-green-400'],
            [__('Cost of Stock'), $amount($this->entriesCost()), 'text-zinc-900 dark:text-zinc-100'],
        ],
        'issued' => [
            [__('Transfers'), $this->issues->where('reason', \App\Models\StockIssue::REASON_TRANSFER)->sum('quantity'), 'text-sky-600 dark:text-sky-400'],
            [__('Internal Use'), $this->issues->where('reason', \App\Models\StockIssue::REASON_INTERNAL_USE)->sum('quantity'), 'text-zinc-900 dark:text-zinc-100'],
            [__('Wastage'), $this->issues->where('reason', \App\Models\StockIssue::REASON_WASTAGE)->sum('quantity'), 'text-red-600 dark:text-red-400'],
            [__('Quantity Issued'), number_format($this->issues->sum('quantity'), 2), 'text-amber-600 dark:text-amber-400'],
        ],
        default => [
            [__('Products'), $totals['products'], 'text-zinc-900 dark:text-zinc-100'],
            [__('Low Stock'), $totals['low'], 'text-amber-600 dark:text-amber-400'],
            [__('Out of Stock'), $totals['out'], 'text-red-600 dark:text-red-400'],
            [__('Stock Value'), $amount($totals['value']), 'text-green-600 dark:text-green-400'],
        ],
    }"
    :footnote="match ($view) {
        'received' => __('Expiry dates within :days days are highlighted. Cost of stock counts deliveries with a unit cost.', ['days' => \App\Livewire\Reports\StockProducts::EXPIRY_WARNING_DAYS]),
        'issued' => __('Issues are recorded under Manage Stock → Issue Products. Reversing one puts the quantity back into stock.'),
        default => __('Received includes transfers in from other outlets. Stock value is priced at the selling price.'),
    }"
>
    @if ($canRecord)
        <x-slot:actions>
            <button type="button" wire:click="openCreate"
                class="inline-flex items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 print:hidden dark:focus:ring-offset-zinc-900">
                <flux:icon.plus variant="mini" class="size-4" />
                {{ __('Record Stock') }}
            </button>
            @if ($outlet !== '')
                <a href="{{ route('manage-stock.issue', (int) $outlet) }}" wire:navigate
                    class="inline-flex items-center gap-2 rounded-md bg-amber-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 print:hidden dark:focus:ring-offset-zinc-900">
                    <flux:icon.arrow-up-tray variant="mini" class="size-4" />
                    {{ __('Issue Products') }}
                </a>
            @endif
        </x-slot:actions>
    @endif

    <x-slot:nav>
        @foreach ($tabs as $key => $label)
            @if (! $loop->first)
                <span class="text-zinc-400 dark:text-zinc-600">|</span>
            @endif
            <button type="button" wire:click="showView('{{ $key }}')"
                @class([
                    'hover:underline',
                    'font-semibold text-zinc-900 dark:text-zinc-100' => $view === $key,
                    'text-sky-600 dark:text-sky-400' => $view !== $key,
                ])>{{ $label }}</button>
        @endforeach
    </x-slot:nav>

    <x-slot:filters>
        <div class="w-full sm:w-56">
            <flux:select wire:model="outlet" :label="__('Outlet')">
                <flux:select.option value="">{{ __('All outlets') }}</flux:select.option>
                @foreach ($this->outlets as $outletOption)
                    <flux:select.option :value="$outletOption->id">{{ $outletOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @if ($this->stockTypes->isNotEmpty())
            <div class="w-full sm:w-56">
                <flux:select wire:model="stockType" :label="__('Stock type')">
                    <flux:select.option value="">{{ __('All stock types') }}</flux:select.option>
                    @foreach ($this->stockTypes as $stockTypeOption)
                        <flux:select.option :value="$stockTypeOption->id">{{ $stockTypeOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif
    </x-slot:filters>

    @if ($view === 'received')
        <table class="w-full text-sm text-zinc-700 dark:text-zinc-300">
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60">
                <tr>
                    <th class="{{ $th }} text-left">{{ __('Date') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Product') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Supplier') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Expiring Date') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Description') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Quantity') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Unit Cost') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Total Cost') }}</th>
                    @if ($canRecord)
                        <th class="{{ $th }} print:hidden"></th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->entries as $entry)
                    @php($expiry = $this->expiryState($entry))
                    <tr wire:key="stock-entry-{{ $entry->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40"
                        title="{{ __('Recorded by :name', ['name' => $entry->recorder?->name ?? '—']) }}">
                        <td class="{{ $td }}">{{ $entry->entry_date->format('d-m-Y') }}</td>
                        <td class="{{ $td }} font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $entry->menuItem->name }}
                            <span class="font-normal text-zinc-500 dark:text-zinc-400">· {{ $entry->menuItem->outlet->name }}</span>
                        </td>
                        <td class="{{ $td }}">{{ $entry->supplier?->name ?? '—' }}</td>
                        <td @class([$td, 'font-medium text-red-600 dark:text-red-400' => $expiry === 'expired', 'font-medium text-amber-600 dark:text-amber-400' => $expiry === 'soon'])>
                            {{ $entry->expiry_date?->format('d-m-Y') ?? '—' }}
                            @if ($expiry === 'expired')
                                <span class="ms-1 text-xs">({{ __('expired') }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $entry->description ?? '—' }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ number_format($entry->quantity, 2) }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ $minor($entry->unit_cost) }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ $minor($entry->totalCost()) }}</td>
                        @if ($canRecord)
                            <td class="{{ $td }} text-right print:hidden">
                                <button type="button" wire:click="delete({{ $entry->id }})" wire:confirm="{{ __('Delete this entry? Its quantity will be taken off the stock on hand.') }}"
                                    class="text-red-600 hover:underline dark:text-red-400">{{ __('Delete') }}</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-zinc-500">{{ __('No stock received in this period.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-100">
                <tr>
                    <td colspan="5" class="{{ $td }} text-base">{{ __('Total') }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ number_format($this->entries->sum('quantity'), 2) }}</td>
                    <td class="{{ $td }}"></td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($this->entriesCost()) }}</td>
                    @if ($canRecord)
                        <td class="print:hidden"></td>
                    @endif
                </tr>
            </tfoot>
        </table>
    @elseif ($view === 'issued')
        <table class="w-full text-sm text-zinc-700 dark:text-zinc-300">
            <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60">
                <tr>
                    <th class="{{ $th }} text-left">{{ __('Date') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Product') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Reason') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Issued To') }}</th>
                    <th class="{{ $th }} text-left">{{ __('Description') }}</th>
                    <th class="{{ $th }} text-right">{{ __('Quantity') }}</th>
                    @if ($canRecord)
                        <th class="{{ $th }} print:hidden"></th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->issues as $issue)
                    <tr wire:key="stock-issue-{{ $issue->id }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40"
                        title="{{ __('Recorded by :name', ['name' => $issue->recorder?->name ?? '—']) }}">
                        <td class="{{ $td }}">{{ $issue->issue_date->format('d-m-Y') }}</td>
                        <td class="{{ $td }} font-medium text-zinc-900 dark:text-zinc-100">
                            {{ $issue->menuItem->name }}
                            <span class="font-normal text-zinc-500 dark:text-zinc-400">· {{ $issue->menuItem->outlet->name }}</span>
                        </td>
                        <td class="{{ $td }}">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $issueColor($issue->reason) }}">{{ $issue->reasonLabel() }}</span>
                        </td>
                        <td class="{{ $td }}">
                            @if ($issue->toMenuItem)
                                {{ $issue->toMenuItem->name }} · {{ $issue->toMenuItem->outlet->name }}
                            @else
                                {{ $issue->issued_to ?? '—' }}
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $issue->description ?? '—' }}</td>
                        <td class="{{ $td }} text-right tabular-nums">{{ number_format($issue->quantity, 2) }}</td>
                        @if ($canRecord)
                            <td class="{{ $td }} text-right print:hidden">
                                <button type="button" wire:click="reverseIssue({{ $issue->id }})" wire:confirm="{{ __('Reverse this issue? The quantity goes back into stock.') }}"
                                    class="text-red-600 hover:underline dark:text-red-400">{{ __('Reverse') }}</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center text-zinc-500">{{ __('No stock issued in this period.') }}</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-100">
                <tr>
                    <td colspan="5" class="{{ $td }} text-base">{{ __('Total') }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ number_format($this->issues->sum('quantity'), 2) }}</td>
                    @if ($canRecord)
                        <td class="print:hidden"></td>
                    @endif
                </tr>
            </tfoot>
        </table>
    @else
    <table class="w-full text-sm text-zinc-700 dark:text-zinc-300">
        <thead class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/60">
            <tr>
                <th class="{{ $th }} text-left">{{ __('Product') }}</th>
                <th class="{{ $th }} text-left">{{ __('Outlet') }}</th>
                <th class="{{ $th }} text-left">{{ __('Category') }}</th>
                <th class="{{ $th }} text-left">{{ __('Stock Type') }}</th>
                <th class="{{ $th }} text-right">{{ __('Price') }}</th>
                <th class="{{ $th }} text-right">{{ __('Received') }}</th>
                <th class="{{ $th }} text-right">{{ __('Issued') }}</th>
                <th class="{{ $th }} text-right">{{ __('Sold') }}</th>
                <th class="{{ $th }} text-right">{{ __('In Stock') }}</th>
                <th class="{{ $th }} text-right">{{ __('Stock Value') }}</th>
                <th class="{{ $th }} text-left">{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($this->rows as $row)
                <tr wire:key="stock-{{ $loop->index }}" class="transition hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                    <td class="{{ $td }} font-medium text-zinc-900 dark:text-zinc-100">{{ $row['name'] }}</td>
                    <td class="{{ $td }}">{{ $row['outlet'] }}</td>
                    <td class="{{ $td }}">{{ $row['category'] }}</td>
                    <td class="{{ $td }}">{{ $row['stock_type'] }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($row['price']) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $row['received'] }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $row['issued'] }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $row['sold'] }}</td>
                    <td class="{{ $td }} text-right font-medium tabular-nums">{{ $row['in_stock'] }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($row['value']) }}</td>
                    <td class="{{ $td }}">
                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBadge($row['status']) }}">{{ __($row['status']) }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="px-4 py-12 text-center text-zinc-500">{{ __('No stock-tracked products. Turn on stock tracking for menu items in Manage Stock → Outlets & menus.') }}</td>
                </tr>
            @endforelse
        </tbody>
        @if ($this->rows !== [])
            <tfoot class="border-t-2 border-zinc-200 bg-zinc-50 font-semibold text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-100">
                <tr>
                    <td colspan="5" class="{{ $td }}">{{ __('Total') }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ array_sum(array_column($this->rows, 'received')) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ array_sum(array_column($this->rows, 'issued')) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ array_sum(array_column($this->rows, 'sold')) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ array_sum(array_column($this->rows, 'in_stock')) }}</td>
                    <td class="{{ $td }} text-right tabular-nums">{{ $amount($totals['value']) }}</td>
                    <td class="{{ $td }}"></td>
                </tr>
            </tfoot>
        @endif
    </table>
    @endif
</x-reports.shell>

@if ($canRecord)
    <flux:modal name="stock-entry-form" class="md:w-[480px]">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ __('Record stock received') }}</flux:heading>

            <flux:select wire:model="entryItem" :label="__('Product')" required>
                <flux:select.option value="">{{ __('Choose a product') }}</flux:select.option>
                @foreach ($this->menuItems as $menuItem)
                    <flux:select.option :value="$menuItem->id">{{ $menuItem->name }} · {{ $menuItem->outlet->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="entrySupplier" :label="__('Supplier')">
                <flux:select.option value="">{{ __('No supplier') }}</flux:select.option>
                @foreach ($this->suppliers as $supplier)
                    <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="entryDate" :label="__('Date received')" required />
                <flux:input type="date" wire:model="entryExpiry" :label="__('Expiring date')" />
            </div>
            <flux:input wire:model="entryDescription" :label="__('Description')" :placeholder="__('e.g. Invoice number')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="number" min="1" step="1" wire:model="entryQuantity" :label="__('Quantity')" required />
                <flux:input type="number" min="0" step="any" wire:model="entryUnitCost" :label="__('Unit cost (:currency)', ['currency' => $this->hotelCurrency()])" />
            </div>
            <flux:text class="text-sm">{{ __('The quantity is added to the product stock on hand. Stock tracking is switched on if it was off.') }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
@endif
</div>
