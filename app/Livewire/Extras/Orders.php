<?php

declare(strict_types=1);

namespace App\Livewire\Extras;

use App\Models\ExtraOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Extra orders')]
class Orders extends Component
{
    use WithPagination;

    #[Url]
    public string $date = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('manage-folios') ?? false, 403);
    }

    public function updatedDate(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, ExtraOrder>
     */
    #[Computed]
    public function orders(): LengthAwarePaginator
    {
        return ExtraOrder::query()
            ->with(['items', 'stay.guest', 'creator'])
            ->when($this->date !== '', fn ($query) => $query->whereDate('order_date', $this->date))
            ->latest('order_date')
            ->latest('id')
            ->paginate(20);
    }

    public function render(): View
    {
        return view('livewire.extras.orders');
    }
}
