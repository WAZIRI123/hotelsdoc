<?php

declare(strict_types=1);

use App\Domain\Folios\Actions\PostFolioCharge;
use App\Domain\Payments\Actions\RecordPayment;
use App\Domain\Payments\Actions\RecordRefund;
use App\Livewire\Folios\Show;
use App\Livewire\Reports\CashUp;
use App\Models\FiscalReceipt;
use App\Models\Folio;
use App\Support\Money;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->folio = Folio::query()->create([
        'hotel_id' => $this->hotel->id,
        'status' => 'open',
        'subtotal' => 100_000_00,
        'total_amount' => 100_000_00,
    ]);
    app(RecordPayment::class)->execute($this->folio, 'cash', Money::fromMajor(100_000), receivedBy: $this->user);
    $this->folio->refresh();
});

it('supersedes the receipt when a settled folio is charged again and issues a new one once paid', function (): void {
    $first = FiscalReceipt::query()->where('folio_id', $this->folio->id)->sole();

    app(PostFolioCharge::class)->price($this->folio, 'pos_order', null, 'Bar', Money::fromMajor(10_000));

    expect($first->fresh()->status)->toBe('superseded')
        ->and($this->folio->fresh()->status)->toBe('open');

    app(RecordPayment::class)->execute($this->folio->fresh(), 'cash', Money::fromMajor(10_000), receivedBy: $this->user);

    $current = FiscalReceipt::query()->where('folio_id', $this->folio->id)->where('status', 'issued')->sole();
    expect($current->id)->not->toBe($first->id)
        ->and($current->total_amount->amount)->toBe(110_000_00);
});

it('re-issues the receipt at once when a credit leaves a settled folio fully paid', function (): void {
    app(PostFolioCharge::class)->price($this->folio, 'room', null, 'Early check-out', Money::fromMajor(-20_000));

    $current = FiscalReceipt::query()->where('folio_id', $this->folio->id)->where('status', 'issued')->sole();
    expect($this->folio->fresh()->status)->toBe('settled')
        ->and($current->total_amount->amount)->toBe(80_000_00);
});

it('refunds a guest credit as a negative payment', function (): void {
    app(PostFolioCharge::class)->price($this->folio, 'room', null, 'Early check-out', Money::fromMajor(-20_000));

    $refund = app(RecordRefund::class)->execute($this->folio, 'cash', Money::fromMajor(20_000), refundedBy: $this->user);

    expect($refund->amount->amount)->toBe(-20_000_00)
        ->and($refund->isRefund())->toBeTrue()
        ->and($this->folio->fresh()->paid_total->amount)->toBe(80_000_00);

    $cashUp = Livewire::test(CashUp::class, ['view' => 'general'])->instance();
    expect($cashUp->grandTotal()->amount)->toBe(80_000_00);
});

it('refuses to refund more than the credit', function (): void {
    app(PostFolioCharge::class)->price($this->folio, 'room', null, 'Early check-out', Money::fromMajor(-20_000));

    app(RecordRefund::class)->execute($this->folio, 'cash', Money::fromMajor(20_001), refundedBy: $this->user);
})->throws(RuntimeException::class, 'can be refunded');

it('records a refund from the folio page', function (): void {
    app(PostFolioCharge::class)->price($this->folio, 'room', null, 'Early check-out', Money::fromMajor(-20_000));

    Livewire::test(Show::class, ['folio' => $this->folio->fresh()])
        ->assertSee('Refund credit')
        ->call('openRefund')
        ->assertSet('refund_amount', 20_000)
        ->set('refund_method', 'cash')
        ->call('refund')
        ->assertHasNoErrors();

    expect($this->folio->fresh()->paid_total->amount)->toBe(80_000_00);
});

it('hides the refund button when there is no credit', function (): void {
    Livewire::test(Show::class, ['folio' => $this->folio])
        ->assertDontSee('Refund credit');
});
