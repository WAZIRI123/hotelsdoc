<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Currencies;

use App\Models\Currency;
use App\Models\Hotel;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('View Currency')]
class Index extends Component
{
    #[Url(as: 'hotel')]
    public ?int $hotelId = null;

    public function mount(): void
    {
        $this->guardSuperAdmin();
    }

    /**
     * @return Collection<int, Hotel>
     */
    #[Computed]
    public function hotels(): Collection
    {
        return Hotel::query()->orderBy('name')->get(['id', 'name', 'currency']);
    }

    /**
     * Runs outside the tenant middleware, so this spans every hotel unless
     * one is picked in the filter.
     *
     * @return Collection<int, Currency>
     */
    #[Computed]
    public function currencies(): Collection
    {
        return Currency::query()
            ->with(['hotel:id,name,currency', 'creator:id,name'])
            ->when($this->hotelId !== null, fn ($query) => $query->where('hotel_id', $this->hotelId))
            ->join('hotels', 'hotels.id', '=', 'currencies.hotel_id')
            ->orderBy('hotels.name')
            ->orderBy('currencies.code')
            ->select('currencies.*')
            ->get();
    }

    public function delete(int $currencyId): void
    {
        $this->guardSuperAdmin();
        $currency = Currency::query()->findOrFail($currencyId);
        $currency->delete();

        unset($this->currencies);
        Flux::toast(variant: 'success', text: __(':code removed.', ['code' => $currency->code]));
    }

    private function guardSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin() ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.admin.currencies.index');
    }
}
