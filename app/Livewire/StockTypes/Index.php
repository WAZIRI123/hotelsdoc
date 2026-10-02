<?php

declare(strict_types=1);

namespace App\Livewire\StockTypes;

use App\Models\StockType;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('View Stock Type')]
class Index extends Component
{
    /** The Manage Stock outlet the user came from, for the back link. */
    #[Url(as: 'outlet')]
    public ?int $outletId = null;

    public function mount(): void
    {
        $this->authorizeManagement();
    }

    /**
     * @return Collection<int, StockType>
     */
    #[Computed]
    public function stockTypes(): Collection
    {
        return StockType::query()
            ->withCount('menuItems')
            ->orderBy('name')
            ->get();
    }

    public function delete(int $id): void
    {
        $this->authorizeManagement();

        StockType::query()->findOrFail($id)->delete();

        Flux::toast(variant: 'success', text: __('Stock type deleted.'));
        unset($this->stockTypes);
    }

    public function render(): View
    {
        return view('livewire.stock-types.index');
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-stock') ?? false, 403);
    }
}
