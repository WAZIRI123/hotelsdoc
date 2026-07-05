<?php

declare(strict_types=1);

namespace App\Livewire\Guests;

use App\Models\Guest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('All Guests')]
class GuestList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Guest>
     */
    #[Computed]
    public function guests(): LengthAwarePaginator
    {
        return Guest::query()
            ->withCount('reservations')
            ->when($this->search, function ($q): void {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term): void {
                    $q->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('nationality', 'like', $term);
                });
            })
            ->orderBy('first_name')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.guests.list');
    }
}
