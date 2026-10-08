<?php

declare(strict_types=1);

use App\Livewire\Folios\RecordPaymentForm;
use App\Livewire\Folios\Show;
use App\Models\Folio;
use App\Models\PaymentMethod;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->folio = Folio::query()->create([
        'hotel_id' => $this->hotel->id,
        'total_amount' => 80_000_00,
        'status' => 'open',
    ]);
});

it('pre-fills the form with the outstanding balance', function (): void {
    Livewire::test(RecordPaymentForm::class, ['folio' => $this->folio])
        ->assertSet('tenderAmount', 80_000);
});

it('records a cash payment via the form', function (): void {
    Livewire::test(RecordPaymentForm::class, ['folio' => $this->folio])
        ->set('method_code', 'cash')
        ->set('tenderAmount', 80_000)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('payment-recorded');

    expect($this->folio->fresh()->status)->toBe('settled')
        ->and($this->folio->fresh()->paid_total->toMajor())->toBe(80_000.0);
});

it('requires a reference when the gateway demands it', function (): void {
    Livewire::test(RecordPaymentForm::class, ['folio' => $this->folio])
        ->set('method_code', 'mpesa')
        ->set('tenderAmount', 50_000)
        ->call('submit')
        ->assertHasErrors('reference');
});

it('rejects a zero amount', function (): void {
    Livewire::test(RecordPaymentForm::class, ['folio' => $this->folio])
        ->set('method_code', 'cash')
        ->set('tenderAmount', 0)
        ->call('submit')
        ->assertHasErrors('tenderAmount');
});

it('stores the payer phone and note and shows them on the folio', function (): void {
    Livewire::test(RecordPaymentForm::class, ['folio' => $this->folio])
        ->set('method_code', 'mpesa')
        ->set('tenderAmount', 30_000)
        ->set('reference', 'MP123')
        ->set('payer_phone', '0712345678')
        ->set('note', 'Paid by employer')
        ->call('submit')
        ->assertHasNoErrors();

    $payment = $this->folio->payments()->sole();
    expect($payment->payer_phone)->toBe('0712345678')
        ->and($payment->note)->toBe('Paid by employer');

    Livewire::test(Show::class, ['folio' => $this->folio])
        ->assertSee('0712345678')
        ->assertSee('Paid by employer');
});

it('only accepts the hotel\'s active payment methods', function (): void {
    PaymentMethod::query()->where('code', 'cash')->update(['is_active' => false]);

    Livewire::test(RecordPaymentForm::class, ['folio' => $this->folio])
        ->set('method_code', 'cash')
        ->set('tenderAmount', 80_000)
        ->call('submit')
        ->assertHasErrors('method_code')
        ->set('method_code', 'not-a-method')
        ->call('submit')
        ->assertHasErrors('method_code');

    expect($this->folio->payments()->count())->toBe(0);
});
