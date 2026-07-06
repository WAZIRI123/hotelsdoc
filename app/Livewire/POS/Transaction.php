<?php

declare(strict_types=1);

namespace App\Livewire\POS;

use App\Models\PosOrder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('POS transaction')]
class Transaction extends Component
{
    public PosOrder $posOrder;

    public function mount(PosOrder $posOrder): void
    {
        $this->posOrder = $posOrder->load([
            'outlet',
            'items.menuItem',
            'folio.guest',
            'folio.stay.reservation',
        ]);
    }

    public function render(): View
    {
        return view('livewire.pos.transaction');
    }
}
