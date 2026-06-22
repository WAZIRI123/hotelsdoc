<?php

declare(strict_types=1);

namespace App\Livewire\POS;

use App\Models\Outlet;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Point of sale')]
class Lobby extends Component
{
    /**
     * @return Collection<int, Outlet>
     */
    #[Computed]
    public function outlets(): Collection
    {
        return Outlet::query()->where('is_active', true)->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.pos.lobby');
    }
}
