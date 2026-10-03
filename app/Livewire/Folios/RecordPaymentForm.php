<?php

declare(strict_types=1);

namespace App\Livewire\Folios;

use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Payments\PaymentGatewayManager;
use App\Models\Folio;
use App\Models\PaymentMethod;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use RuntimeException;

class RecordPaymentForm extends Component
{
    public Folio $folio;

    #[Validate('required|string')]
    public string $method_code = '';

    /** Whole TSh, never cents at the UI layer. */
    #[Validate('required|numeric|min:1')]
    public ?int $amount_tsh = null;

    #[Validate('nullable|string|max:80')]
    public string $reference = '';

    #[Validate('nullable|string|max:32')]
    public string $payer_phone = '';

    #[Validate('nullable|string|max:300')]
    public string $note = '';

    public function mount(Folio $folio): void
    {
        $this->folio = $folio;
        $this->amount_tsh = (int) $folio->total_amount->subtract($folio->paid_total)->toMajor();
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    #[Computed]
    public function methods(): Collection
    {
        return PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }

    #[Computed]
    public function selectedRequiresReference(): bool
    {
        if ($this->method_code === '') {
            return false;
        }

        try {
            return app(PaymentGatewayManager::class)->for($this->method_code)->requiresReference();
        } catch (\Throwable) {
            return false;
        }
    }

    public function submit(RecordPayment $action): void
    {
        $this->validate();
        $this->validate(['method_code' => [Rule::in($this->methods()->pluck('code')->all())]]);

        if ($this->selectedRequiresReference()) {
            $this->validate(['reference' => 'required|string|max:80']);
        }

        try {
            $action->execute(
                folio: $this->folio,
                methodCode: $this->method_code,
                amount: Money::fromMajor((int) $this->amount_tsh),
                reference: $this->reference ?: null,
                payerPhone: $this->payer_phone ?: null,
                receivedBy: Auth::user(),
                note: $this->note ?: null,
            );
        } catch (RuntimeException|InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: 'Payment recorded.');
        Flux::modal('record-payment')->close();
        $this->dispatch('payment-recorded');
    }

    public function render(): View
    {
        return view('livewire.folios.record-payment-form');
    }
}
