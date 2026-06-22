<?php

declare(strict_types=1);

use App\Domain\Payments\Actions\RecordPayment;
use App\Models\FiscalReceipt;
use App\Models\Folio;
use App\Support\Money;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->folio = Folio::query()->create([
        'hotel_id' => $this->hotel->id,
        'status' => 'open',
        'total_amount' => 100_000_00,
    ]);
    $this->action = app(RecordPayment::class);
});

it('records a confirmed cash payment and credits the folio', function (): void {
    $payment = $this->action->execute(
        folio: $this->folio,
        methodCode: 'cash',
        amount: Money::fromMajor(40_000),
        receivedBy: $this->user,
    );

    expect($payment->status)->toBe('confirmed')
        ->and($payment->confirmed_at)->not->toBeNull()
        ->and($payment->received_by)->toBe($this->user->id);

    expect($this->folio->fresh()->paid_total->toMajor())->toBe(40_000.0)
        ->and($this->folio->fresh()->status)->toBe('open');
});

it('settles the folio and issues a fiscal receipt when fully paid', function (): void {
    $this->action->execute(
        folio: $this->folio,
        methodCode: 'cash',
        amount: Money::fromMajor(100_000),
        receivedBy: $this->user,
    );

    $fresh = $this->folio->fresh();
    expect($fresh->status)->toBe('settled')
        ->and($fresh->settled_at)->not->toBeNull();

    $receipt = FiscalReceipt::query()->where('folio_id', $this->folio->id)->first();
    expect($receipt)->not->toBeNull()
        ->and($receipt->status)->toBe('issued')
        ->and($receipt->receipt_no)->toStartWith('R-');
});

it('requires a reference for mobile money payments', function (): void {
    $this->action->execute(
        folio: $this->folio,
        methodCode: 'mpesa',
        amount: Money::fromMajor(50_000),
    );
})->throws(InvalidArgumentException::class);

it('records mobile money with reference and credits folio', function (): void {
    $payment = $this->action->execute(
        folio: $this->folio,
        methodCode: 'mpesa',
        amount: Money::fromMajor(50_000),
        reference: 'QXR12345',
        payerPhone: '+255712000000',
    );

    expect($payment->method_code)->toBe('mpesa')
        ->and($payment->reference)->toBe('QXR12345');
});

it('refuses to record a payment against a voided folio', function (): void {
    $this->folio->update(['status' => 'void']);

    $this->action->execute(
        folio: $this->folio->fresh(),
        methodCode: 'cash',
        amount: Money::fromMajor(1_000),
    );
})->throws(RuntimeException::class, 'voided');

it('refuses zero or negative amounts', function (): void {
    $this->action->execute(
        folio: $this->folio,
        methodCode: 'cash',
        amount: Money::zero(),
    );
})->throws(RuntimeException::class, 'positive');
