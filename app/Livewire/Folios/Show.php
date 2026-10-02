<?php

declare(strict_types=1);

namespace App\Livewire\Folios;

use App\Domain\Folios\Actions\ApplyFolioDiscount;
use App\Domain\Folios\Actions\VoidFolio;
use App\Domain\Notifications\BeemSmsClient;
use App\Domain\Payments\Actions\RecordRefund;
use App\Domain\Payments\Actions\VoidPayment;
use App\Domain\Tenancy\TenantContext;
use App\Mail\FolioBillMail;
use App\Models\FiscalReceipt;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Support\Money;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use RuntimeException;
use Throwable;

#[Title('Folio')]
class Show extends Component
{
    public Folio $folio;

    #[Validate('required|email:rfc|max:200')]
    public string $recipient_email = '';

    #[Validate('required|string|max:32')]
    public string $recipient_phone = '';

    public string $refund_method = '';

    public ?int $refund_amount = null;

    public string $refund_reference = '';

    public ?int $voidingPaymentId = null;

    public string $void_reason = '';

    public ?int $discount_amount = null;

    public string $discount_reason = '';

    public function mount(Folio $folio): void
    {
        $this->reloadFolio($folio);
        $this->recipient_email = $folio->guest->email ?? '';
        $this->recipient_phone = $folio->guest->phone ?? '';
    }

    public function openPayment(): void
    {
        Flux::modal('record-payment')->show();
    }

    public function openSendEmail(): void
    {
        $this->resetErrorBag('recipient_email');
        Flux::modal('send-bill-email')->show();
    }

    public function openSendSms(): void
    {
        $this->resetErrorBag('recipient_phone');
        Flux::modal('send-bill-sms')->show();
    }

    public function sendByEmail(): void
    {
        $this->validateOnly('recipient_email');

        try {
            Mail::to($this->recipient_email)->send(new FolioBillMail($this->folio));
        } catch (Throwable $e) {
            Flux::toast(variant: 'danger', text: __('Could not send email: ').$e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Bill emailed to :email', ['email' => $this->recipient_email]));
        Flux::modal('send-bill-email')->close();
    }

    public function sendBySms(BeemSmsClient $sms): void
    {
        $this->validateOnly('recipient_phone');

        try {
            $sms->send($this->recipient_phone, $this->buildSmsMessage());
        } catch (Throwable $e) {
            Flux::toast(variant: 'danger', text: __('Could not send SMS: ').$e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Bill SMS sent to :phone', ['phone' => $this->recipient_phone]));
        Flux::modal('send-bill-sms')->close();
    }

    /**
     * What the guest has paid beyond what they owe.
     */
    #[Computed]
    public function creditBalance(): Money
    {
        $credit = $this->folio->paid_total->subtract($this->folio->total_amount);

        return $credit->isPositive() ? $credit : Money::zero($credit->currency);
    }

    public function canRefund(): bool
    {
        return (Auth::user()?->can('manage-payments') ?? false)
            && $this->folio->status !== 'void'
            && $this->creditBalance()->isPositive();
    }

    /**
     * @return Collection<int, PaymentMethod>
     */
    #[Computed]
    public function refundMethods(): Collection
    {
        return PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    public function openRefund(): void
    {
        abort_unless($this->canRefund(), 403);

        $this->refund_method = '';
        $this->refund_reference = '';
        $this->refund_amount = (int) $this->creditBalance()->toMajor();
        $this->resetErrorBag(['refund_method', 'refund_amount', 'refund_reference']);
        Flux::modal('refund-credit')->show();
    }

    public function refund(RecordRefund $action): void
    {
        abort_unless($this->canRefund(), 403);

        $this->validate([
            'refund_method' => ['required', Rule::in($this->refundMethods()->pluck('code')->all())],
            'refund_amount' => ['required', 'integer', 'min:1'],
            'refund_reference' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $action->execute(
                folio: $this->folio,
                methodCode: $this->refund_method,
                amount: Money::fromMajor((int) $this->refund_amount, $this->folio->currency),
                reference: $this->refund_reference ?: null,
                refundedBy: Auth::user(),
            );
        } catch (RuntimeException $e) {
            $this->addError('refund_amount', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Refund recorded.'));
        Flux::modal('refund-credit')->close();
        $this->reloadFolio($this->folio);
    }

    public function canDiscount(): bool
    {
        return (Auth::user()?->can('manage-payments') ?? false)
            && $this->folio->acceptsCharges()
            && $this->folio->total_amount->isPositive();
    }

    public function openDiscount(): void
    {
        abort_unless($this->canDiscount(), 403);

        $this->reset(['discount_amount', 'discount_reason']);
        $this->resetErrorBag(['discount_amount', 'discount_reason']);
        Flux::modal('add-discount')->show();
    }

    public function applyDiscount(ApplyFolioDiscount $action): void
    {
        abort_unless($this->canDiscount(), 403);

        $this->validate([
            'discount_amount' => ['required', 'integer', 'min:1'],
            'discount_reason' => ['required', 'string', 'max:120'],
        ]);

        try {
            $action->execute(
                $this->folio,
                Money::fromMajor((int) $this->discount_amount, $this->folio->currency),
                $this->discount_reason,
            );
        } catch (RuntimeException $e) {
            $this->addError('discount_amount', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Discount applied.'));
        Flux::modal('add-discount')->close();
        $this->reloadFolio($this->folio);
    }

    public function canVoid(): bool
    {
        return (Auth::user()?->can('manage-payments') ?? false) && $this->folio->status !== 'void';
    }

    public function startVoidPayment(int $paymentId): void
    {
        abort_unless($this->canVoid(), 403);

        $this->voidingPaymentId = $this->folio->payments()->whereKey($paymentId)->firstOrFail()->id;
        $this->void_reason = '';
        $this->resetErrorBag('void_reason');
        Flux::modal('void-payment')->show();
    }

    public function voidPayment(VoidPayment $action): void
    {
        abort_unless($this->canVoid(), 403);

        $this->validate(['void_reason' => ['required', 'string', 'max:255']]);

        try {
            $action->execute(
                Payment::query()->where('folio_id', $this->folio->id)->findOrFail($this->voidingPaymentId),
                $this->void_reason,
                Auth::user(),
            );
        } catch (RuntimeException $e) {
            $this->addError('void_reason', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Payment voided.'));
        Flux::modal('void-payment')->close();
        $this->voidingPaymentId = null;
        $this->reloadFolio($this->folio);
    }

    public function startVoidFolio(): void
    {
        abort_unless($this->canVoid(), 403);

        $this->void_reason = '';
        $this->resetErrorBag('void_reason');
        Flux::modal('void-folio')->show();
    }

    public function voidFolio(VoidFolio $action): void
    {
        abort_unless($this->canVoid(), 403);

        $this->validate(['void_reason' => ['required', 'string', 'max:255']]);

        try {
            $action->execute($this->folio, $this->void_reason, Auth::user());
        } catch (RuntimeException $e) {
            $this->addError('void_reason', $e->getMessage());

            return;
        }

        Flux::toast(variant: 'success', text: __('Folio voided.'));
        Flux::modal('void-folio')->close();
        $this->reloadFolio($this->folio);
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
        unset($this->creditBalance);

        $this->folio = $folio->fresh(['charges', 'payments', 'guest', 'stay.reservation'])
            ?? $folio->load(['charges', 'payments', 'guest', 'stay.reservation']);
    }

    private function buildSmsMessage(): string
    {
        $hotel = app(TenantContext::class)->current();
        $balance = $this->folio->total_amount->subtract($this->folio->paid_total);
        $guest = $this->folio->guest?->fullName() ?? __('Guest');

        return __('Hi :guest, your bill :ref at :hotel is :total. Balance due: :balance. Thank you!', [
            'guest' => $guest,
            'ref' => $this->folio->reference,
            'hotel' => $hotel->name,
            'total' => $this->folio->total_amount->format(),
            'balance' => $balance->format(),
        ]);
    }

    public function render(): View
    {
        return view('livewire.folios.show');
    }
}
