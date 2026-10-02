<?php

declare(strict_types=1);

namespace App\Livewire\Stock;

use App\Domain\Stock\Actions\IssueStock;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\StockIssue;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

/**
 * Takes stock off an outlet's products for transfers to other outlets,
 * internal use or wastage, so stock on hand matches what is on the shelf.
 */
#[Title('Issue Products')]
class Issue extends Component
{
    public Outlet $outlet;

    public string $itemId = '';

    public string $quantity = '';

    public string $reason = StockIssue::REASON_INTERNAL_USE;

    public string $toItemId = '';

    public string $issuedTo = '';

    public string $issueDate = '';

    public string $description = '';

    public function mount(Outlet $outlet): void
    {
        $this->authorizeManagement();

        $this->outlet = $outlet;
        $this->issueDate = CarbonImmutable::today()->toDateString();
    }

    /**
     * Stock-tracked products of this outlet, the only ones stock can be issued from.
     *
     * @return Collection<int, MenuItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return MenuItem::query()
            ->where('outlet_id', $this->outlet->id)
            ->where('track_stock', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Active products of other outlets a transfer can go to.
     *
     * @return Collection<int, MenuItem>
     */
    #[Computed]
    public function transferTargets(): Collection
    {
        return MenuItem::query()
            ->with('outlet')
            ->where('outlet_id', '!=', $this->outlet->id)
            ->where('is_active', true)
            ->orderBy('outlet_id')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedItem(): ?MenuItem
    {
        return $this->itemId !== '' ? $this->items()->firstWhere('id', (int) $this->itemId) : null;
    }

    /**
     * @return Collection<int, StockIssue>
     */
    #[Computed]
    public function recentIssues(): Collection
    {
        return StockIssue::query()
            ->with(['menuItem', 'toMenuItem.outlet', 'recorder'])
            ->whereHas('menuItem', fn ($query) => $query->where('outlet_id', $this->outlet->id))
            ->latest('issue_date')
            ->latest('id')
            ->limit(10)
            ->get();
    }

    public function save(IssueStock $issueStock): void
    {
        $this->authorizeManagement();

        $isTransfer = $this->reason === StockIssue::REASON_TRANSFER;
        $selectedItem = $this->selectedItem();

        $validated = $this->validate([
            'itemId' => ['required', Rule::in($this->items()->modelKeys())],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.max(1, $selectedItem->stock_quantity ?? 1)],
            'reason' => ['required', Rule::in(array_keys(StockIssue::REASONS))],
            'toItemId' => [Rule::requiredIf($isTransfer), 'nullable', Rule::in($this->transferTargets()->modelKeys())],
            'issuedTo' => ['nullable', 'string', 'max:120'],
            'issueDate' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'quantity.max' => __('Only :max in stock.', ['max' => $selectedItem->stock_quantity ?? 0]),
            'toItemId.required' => __('Choose the product that receives the stock.'),
        ], [
            'itemId' => __('product'),
            'toItemId' => __('receiving product'),
            'issuedTo' => __('issued to'),
            'issueDate' => __('date'),
        ]);

        try {
            $issueStock->execute(
                item: $selectedItem,
                quantity: (int) $validated['quantity'],
                reason: $validated['reason'],
                issueDate: CarbonImmutable::parse($validated['issueDate']),
                transferTo: $isTransfer ? $this->transferTargets()->firstWhere('id', (int) $validated['toItemId']) : null,
                issuedTo: $isTransfer ? null : ($validated['issuedTo'] ?: null),
                description: $validated['description'] ?: null,
                recordedBy: Auth::user(),
            );
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
        }

        Flux::toast(variant: 'success', text: __('Stock issued.'));

        $this->reset(['itemId', 'quantity', 'toItemId', 'issuedTo', 'description']);
        unset($this->items, $this->transferTargets, $this->selectedItem, $this->recentIssues);
    }

    public function reverse(int $id, IssueStock $issueStock): void
    {
        $this->authorizeManagement();

        $issue = StockIssue::query()
            ->whereHas('menuItem', fn ($query) => $query->where('outlet_id', $this->outlet->id))
            ->findOrFail($id);

        $issueStock->reverse($issue);

        Flux::toast(variant: 'success', text: __('Issue reversed.'));
        unset($this->items, $this->selectedItem, $this->recentIssues);
    }

    public function render(): View
    {
        return view('livewire.stock.issue');
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-stock') ?? false, 403);
    }
}
