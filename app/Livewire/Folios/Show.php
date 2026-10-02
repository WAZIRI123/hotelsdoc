<?php

declare(strict_types=1);

namespace App\Livewire\Folios;

use App\Domain\Notifications\BeemSmsClient;
use App\Domain\Tenancy\TenantContext;
use App\Mail\FolioBillMail;
use App\Models\FiscalReceipt;
use App\Models\Folio;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Throwable;

#[Title('Folio')]
class Show extends Component
{
    public Folio $folio;

    #[Validate('required|email:rfc|max:200')]
    public string $recipient_email = '';

    #[Validate('required|string|max:32')]
    public string $recipient_phone = '';

    public function mount(Folio $folio): void
    {
        $this->reloadFolio($folio);
        $this->recipient_email = $folio->guest?->email ?? '';
        $this->recipient_phone = $folio->guest?->phone ?? '';
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
