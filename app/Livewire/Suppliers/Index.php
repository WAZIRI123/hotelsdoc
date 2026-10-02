<?php

declare(strict_types=1);

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('View Supplier')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** The Manage Stock outlet the user came from, for the back link. */
    #[Url(as: 'outlet')]
    public ?int $outletId = null;

    public function mount(): void
    {
        $this->authorizeManagement();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Supplier>
     */
    #[Computed]
    public function suppliers(): LengthAwarePaginator
    {
        return Supplier::query()
            ->withCount('stockEntries')
            ->when($this->search, function ($query): void {
                $term = '%'.$this->search.'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('contact_person', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('tin', 'like', $term);
                });
            })
            ->orderBy('name')
            ->paginate(15);
    }

    public function delete(int $id): void
    {
        $this->authorizeManagement();

        Supplier::query()->findOrFail($id)->delete();

        Flux::toast(variant: 'success', text: __('Supplier deleted.'));
        unset($this->suppliers);
    }

    public function render(): View
    {
        return view('livewire.suppliers.index');
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-stock') ?? false, 403);
    }
}
