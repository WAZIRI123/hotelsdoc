<?php

declare(strict_types=1);

namespace App\Livewire\Admin\InvoiceFooters;

use App\Models\InvoiceFooter;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('View Invoice Footer')]
class Index extends Component
{
    public function mount(): void
    {
        $this->guardSuperAdmin();
    }

    /**
     * Runs outside the tenant middleware, so this spans every hotel.
     *
     * @return Collection<int, InvoiceFooter>
     */
    #[Computed]
    public function footers(): Collection
    {
        return InvoiceFooter::query()
            ->with(['hotel:id,name', 'creator:id,name', 'editor:id,name'])
            ->join('hotels', 'hotels.id', '=', 'invoice_footers.hotel_id')
            ->orderBy('hotels.name')
            ->select('invoice_footers.*')
            ->get();
    }

    public function delete(int $footerId): void
    {
        $this->guardSuperAdmin();
        InvoiceFooter::query()->findOrFail($footerId)->delete();

        unset($this->footers);
        Flux::toast(variant: 'success', text: __('Invoice footer removed.'));
    }

    private function guardSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin() ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.admin.invoice-footers.index');
    }
}
