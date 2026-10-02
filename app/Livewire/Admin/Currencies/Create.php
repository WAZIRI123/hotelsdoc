<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Currencies;

use App\Models\Currency;
use App\Models\Hotel;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Add Currency')]
class Create extends Component
{
    #[Url(as: 'hotel')]
    public ?int $hotelId = null;

    public string $code = '';

    public string $name = '';

    public string $symbol = '';

    public string $exchangeRate = '';

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

    #[Computed]
    public function selectedHotel(): ?Hotel
    {
        return $this->hotelId !== null ? $this->hotels->firstWhere('id', $this->hotelId) : null;
    }

    public function updatedCode(): void
    {
        $this->code = Str::upper(trim($this->code));
    }

    public function save(): void
    {
        $this->guardSuperAdmin();
        $this->code = Str::upper(trim($this->code));

        $validated = $this->validate([
            'hotelId' => ['required', 'integer', Rule::exists('hotels', 'id')],
            'code' => [
                'required', 'alpha:ascii', 'size:3',
                Rule::unique('currencies', 'code')->where('hotel_id', $this->hotelId),
                Rule::notIn([$this->selectedHotel?->currency]),
            ],
            'name' => 'required|string|max:60',
            'symbol' => 'nullable|string|max:8',
            'exchangeRate' => 'required|numeric|gt:0|max:999999999',
        ], [
            'code.unique' => __('This organization already has that currency.'),
            'code.not_in' => __('This is already the organization\'s base currency.'),
        ], [
            'hotelId' => __('organization'),
            'exchangeRate' => __('exchange rate'),
        ]);

        $currency = new Currency([
            'hotel_id' => $validated['hotelId'],
            'code' => $validated['code'],
            'name' => $validated['name'],
            'symbol' => $validated['symbol'] ?: null,
            'exchange_rate' => $validated['exchangeRate'],
        ]);
        $currency->forceFill(['created_by' => Auth::id()])->save();

        Flux::toast(variant: 'success', text: __(':code added to :hotel.', ['code' => $currency->code, 'hotel' => $this->selectedHotel?->name]));

        $this->reset('code', 'name', 'symbol', 'exchangeRate');
    }

    private function guardSuperAdmin(): void
    {
        abort_unless(Auth::user()?->isSuperAdmin() ?? false, 403);
    }

    public function render(): View
    {
        return view('livewire.admin.currencies.create');
    }
}
