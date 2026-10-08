<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domain\Payments\Tender;
use App\Models\AcceptedCurrency;
use App\Support\HotelCurrency;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * Lets a payment form take money in the hotel currency or in any of the
 * hotel's accepted currencies, converting it to the hotel currency at the
 * rate set for that currency.
 *
 * A blank amount means the customer paid exactly what is due, in the chosen
 * currency.
 */
trait TakesTender
{
    /** Code of the currency the customer pays in; '' is the hotel currency. */
    public string $tenderCurrency = '';

    /** What the customer handed over, in whole units of the tender currency. */
    public int|float|string|null $tenderAmount = null;

    /**
     * What this payment has to cover, in the hotel currency.
     */
    abstract protected function tenderDue(): Money;

    /**
     * @return Collection<int, AcceptedCurrency>
     */
    #[Computed]
    public function acceptedCurrencies(): Collection
    {
        return AcceptedCurrency::query()
            ->where('is_active', true)
            ->where('code', '!=', HotelCurrency::current())
            ->orderBy('code')
            ->get();
    }

    /**
     * The tender as entered and what it comes to against what is due, for
     * the form to show while the cashier types.
     *
     * @return array{tender: Tender|null, due: Money, dueInTender: Money, change: Money|null, short: Money|null}
     */
    #[Computed]
    public function tenderSummary(): array
    {
        $due = $this->tenderDue();
        $tender = $this->currentTender();
        $difference = $tender?->localValue()->subtract($due);

        return [
            'tender' => $tender,
            'due' => $due,
            'dueInTender' => $this->dueInTenderCurrency(),
            'change' => $tender?->changeFor($due),
            'short' => $difference?->isNegative() ? $difference->multiply(-1) : null,
        ];
    }

    public function updatedTenderCurrency(): void
    {
        $this->tenderCurrency = $this->selectedCurrency()->code ?? '';
        $this->tenderAmount = $this->defaultTenderAmount();
    }

    /**
     * Back to the hotel currency, with the amount reset to its default.
     */
    protected function resetTender(): void
    {
        $this->tenderCurrency = '';
        $this->tenderAmount = $this->defaultTenderAmount();
        unset($this->tenderSummary);
    }

    /**
     * The amount the field starts with; null leaves it blank (exactly what is due).
     */
    protected function defaultTenderAmount(): int|float|null
    {
        return null;
    }

    /**
     * What is due, converted to the chosen currency (rounded up to the cent).
     */
    protected function dueInTenderCurrency(): Money
    {
        $due = $this->tenderDue();
        $currency = $this->selectedCurrency();

        return $currency === null ? $due : Tender::convertFromLocal($due, $currency->code, $currency->rate);
    }

    /**
     * The tender as entered, or null while it is not a positive amount.
     */
    protected function currentTender(): ?Tender
    {
        $currency = $this->selectedCurrency();

        if ($this->tenderAmount === null || $this->tenderAmount === '') {
            $amount = $this->dueInTenderCurrency();
        } elseif (is_numeric($this->tenderAmount)) {
            $amount = Money::fromMajor((float) $this->tenderAmount, $currency->code ?? HotelCurrency::current());
        } else {
            return null;
        }

        if (! $amount->isPositive()) {
            return null;
        }

        return $currency === null
            ? Tender::local($amount)
            : Tender::foreign($amount, $currency->rate, HotelCurrency::current());
    }

    /**
     * The tender, after checking the currency is accepted and the amount is a
     * positive number.
     *
     * @param  bool  $mustCover  Whether it has to cover everything that is due
     */
    protected function validatedTender(bool $mustCover): Tender
    {
        if ($this->tenderCurrency !== '' && $this->selectedCurrency() === null) {
            throw ValidationException::withMessages(['tenderCurrency' => __('This currency is not accepted.')]);
        }

        $this->validate(['tenderAmount' => ['nullable', 'numeric', 'gt:0']], [], ['tenderAmount' => __('amount')]);

        $tender = $this->currentTender()
            ?? throw ValidationException::withMessages(['tenderAmount' => __('Enter the amount received.')]);

        $due = $this->tenderDue();

        if ($mustCover && ! $tender->covers($due)) {
            throw ValidationException::withMessages(['tenderAmount' => __(':tendered (:local) does not cover :due.', [
                'tendered' => $tender->amount->formatExact(),
                'local' => $tender->localValue()->format(),
                'due' => $due->format(),
            ])]);
        }

        return $tender;
    }

    private function selectedCurrency(): ?AcceptedCurrency
    {
        if ($this->tenderCurrency === '') {
            return null;
        }

        return $this->acceptedCurrencies()->firstWhere('code', $this->tenderCurrency);
    }
}
