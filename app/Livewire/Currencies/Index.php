<?php

declare(strict_types=1);

namespace App\Livewire\Currencies;

use App\Domain\Tenancy\TenantContext;
use App\Models\AcceptedCurrency;
use App\Models\Payment;
use App\Support\HotelCurrency;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The foreign currencies a hotel takes payment in, each with its rate
 * against the hotel's own currency.
 */
#[Title('Currencies')]
class Index extends Component
{
    public ?int $editingId = null;

    public string $code = '';

    public string $rate = '';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorizeManagement();
    }

    /**
     * @return Collection<int, AcceptedCurrency>
     */
    #[Computed]
    public function currencies(): Collection
    {
        return AcceptedCurrency::query()->with('updatedBy:id,name')->orderBy('code')->get();
    }

    #[Computed]
    public function localCurrency(): string
    {
        return HotelCurrency::current();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'code', 'rate']);
        $this->is_active = true;
        $this->resetValidation();
        Flux::modal('currency-form')->show();
    }

    public function openEdit(int $id): void
    {
        $currency = AcceptedCurrency::query()->findOrFail($id);
        $this->editingId = $currency->id;
        $this->code = $currency->code;
        $this->rate = $currency->displayRate();
        $this->is_active = $currency->is_active;
        $this->resetValidation();
        Flux::modal('currency-form')->show();
    }

    public function save(): void
    {
        $this->authorizeManagement();

        $this->code = strtoupper(trim($this->code));

        $this->validate([
            'code' => [
                'required', 'alpha:ascii', 'size:3',
                Rule::notIn([$this->localCurrency()]),
                Rule::unique('accepted_currencies', 'code')
                    ->where('hotel_id', app(TenantContext::class)->id())
                    ->ignore($this->editingId),
            ],
            'rate' => ['required', 'numeric', 'gt:0', 'max:999999999'],
        ], [
            'code.not_in' => __(':currency is the hotel currency; it is always accepted.', ['currency' => $this->localCurrency()]),
        ]);

        $attributes = [
            'code' => $this->code,
            'rate' => $this->rate,
            'is_active' => $this->is_active,
            'updated_by' => Auth::id(),
        ];

        if ($this->editingId) {
            AcceptedCurrency::query()->findOrFail($this->editingId)->update($attributes);
            Flux::toast(variant: 'success', text: __('Currency updated.'));
        } else {
            AcceptedCurrency::query()->create($attributes);
            Flux::toast(variant: 'success', text: __('Currency added.'));
        }

        Flux::modal('currency-form')->close();
        unset($this->currencies);
    }

    /**
     * Payments already taken keep the rate they were taken at, so a currency
     * that has been used is only deactivated.
     */
    public function delete(int $id): void
    {
        $this->authorizeManagement();

        $currency = AcceptedCurrency::query()->findOrFail($id);

        if (Payment::query()->where('tendered_currency', $currency->code)->exists()) {
            $currency->update(['is_active' => false, 'updated_by' => Auth::id()]);
            Flux::toast(text: __('Payments were taken in this currency, so it was deactivated instead of deleted.'));
        } else {
            $currency->delete();
            Flux::toast(variant: 'success', text: __('Currency deleted.'));
        }

        unset($this->currencies);
    }

    public function render(): View
    {
        return view('livewire.currencies.index');
    }

    private function authorizeManagement(): void
    {
        abort_unless(Auth::user()?->can('manage-hotel') ?? false, 403);
    }
}
