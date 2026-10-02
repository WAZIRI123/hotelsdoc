<?php

declare(strict_types=1);

namespace App\Livewire\POS;

use App\Domain\POS\Actions\VoidPosOrder;
use App\Models\PosOrder;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use RuntimeException;

#[Title('POS transaction')]
class Transaction extends Component
{
    public PosOrder $posOrder;

    public string $void_reason = '';

    public function mount(PosOrder $posOrder): void
    {
        $this->loadOrder($posOrder);
    }

    public function canVoid(): bool
    {
        return $this->posOrder->status === 'billed' && (Auth::user()?->can('manage-payments') ?? false);
    }

    public function startVoid(): void
    {
        abort_unless($this->canVoid(), 403);

        $this->void_reason = '';
        $this->resetErrorBag('void_reason');
        Flux::modal('void-order')->show();
    }

    public function voidOrder(VoidPosOrder $action): void
    {
        abort_unless($this->canVoid(), 403);

        $this->validate(['void_reason' => ['required', 'string', 'max:255']]);

        try {
            $action->execute($this->posOrder, $this->void_reason, Auth::user());
        } catch (RuntimeException $e) {
            $this->addError('void_reason', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Order voided.'));
        Flux::modal('void-order')->close();
        $this->loadOrder($this->posOrder);
    }

    public function render(): View
    {
        return view('livewire.pos.transaction');
    }

    private function loadOrder(PosOrder $posOrder): void
    {
        $this->posOrder = ($posOrder->fresh() ?? $posOrder)->load([
            'outlet',
            'items.menuItem',
            'folio.guest',
            'folio.stay.reservation',
        ]);
    }
}
