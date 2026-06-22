<?php

declare(strict_types=1);

use App\Livewire\Folios\RecordPaymentForm;
use App\Models\Folio;
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
        ->assertSet('amount_tsh', 80_000);
});

it('records a cash payment via the form', function (): void {
    Livewire::test(RecordPaymentForm::class, ['folio' => $this->folio])
        ->set('method_code', 'cash')
        ->set('amount_tsh', 80_000)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('payment-recorded');

    expect($this->folio->fresh()->status)->toBe('settled')
        ->and($this->folio->fresh()->paid_total->toMajor())->toBe(80_000.0);
});

it('requires a reference when the gateway demands it', function (): void {
    Livewire::test(RecordPaymentForm::class, ['folio' => $this->folio])
        ->set('method_code', 'mpesa')
        ->set('amount_tsh', 50_000)
        ->call('submit')
        ->assertHasErrors('reference');
});

it('rejects a zero amount', function (): void {
    Livewire::test(RecordPaymentForm::class, ['folio' => $this->folio])
        ->set('method_code', 'cash')
        ->set('amount_tsh', 0)
        ->call('submit')
        ->assertHasErrors('amount_tsh');
});
