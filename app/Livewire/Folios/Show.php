<?php

declare(strict_types=1);

namespace App\Livewire\Folios;

use App\Models\FiscalReceipt;
use App\Models\Folio;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Folio')]
class Show extends Component
{
    public Folio $folio;

    public function mount(Folio $folio): void
    {
        $this->reloadFolio($folio);
    }

    public function openPayment(): void
    {
        Flux::modal('record-payment')->show();
    }

    #[On('payment-recorded')]
    public function onPaymentRecorded(): void
    {
        $this->reloadFolio($this->folio);
    }

    #[Computed]
    public function fiscalReceipt(): ?FiscalReceipt
    {
        return FiscalReceipt::query()
            ->where('folio_id', $this->folio->id)
            ->where('status', 'issued')
            ->latest()
            ->first();
    }

    private function reloadFolio(Folio $folio): void
    {
        $this->folio = $folio->fresh(['charges', 'payments', 'guest', 'stay.reservation'])
            ?? $folio->load(['charges', 'payments', 'guest', 'stay.reservation']);
    }

    public function render(): View
    {
        return view('livewire.folios.show');
    }
}
