<?php

declare(strict_types=1);

namespace App\Livewire\Reservations;

use App\Models\Reservation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Reservations')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $filterStatus = 'all';

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Reservation>
     */
    #[Computed]
    public function reservations(): LengthAwarePaginator
    {
        return Reservation::query()
            ->with(['guest', 'rooms.room'])
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->search, function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term): void {
                    $q->where('confirmation_no', 'like', $term)
                        ->orWhereHas('guest', fn ($q) => $q
                            ->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('phone', 'like', $term));
                });
            })
            ->latest('arrival')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.reservations.index');
    }
}
