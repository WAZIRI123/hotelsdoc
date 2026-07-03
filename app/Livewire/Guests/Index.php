<?php

declare(strict_types=1);

namespace App\Livewire\Guests;

use App\Models\Room;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Guests')]
class Index extends Component
{
    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()
            ->with('roomType')
            ->where('is_active', true)
            ->orderBy('number')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.guests.index');
    }
}
