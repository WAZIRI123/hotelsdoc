<?php

declare(strict_types=1);

use App\Domain\Fiscal\Actions\IssueReceiptForFolio;
use App\Models\FiscalReceipt;
use App\Models\Folio;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
    $this->action = app(IssueReceiptForFolio::class);
});

it('issues a sequential receipt for a folio', function (): void {
    $folio = Folio::query()->create([
        'hotel_id' => $this->hotel->id,
        'total_amount' => 50_000_00,
        'status' => 'settled',
    ]);

    $receipt = $this->action->execute($folio);

    expect($receipt->sequence)->toBe(1)
        ->and($receipt->receipt_no)->toBe('R-000001')
        ->and($receipt->status)->toBe('issued')
        ->and($receipt->provider)->toBe('internal');
});

it('is idempotent — returns the existing receipt instead of issuing again', function (): void {
    $folio = Folio::query()->create([
        'hotel_id' => $this->hotel->id,
        'total_amount' => 50_000_00,
        'status' => 'settled',
    ]);

    $first = $this->action->execute($folio);
    $second = $this->action->execute($folio);

    expect($second->id)->toBe($first->id)
        ->and(FiscalReceipt::query()->where('folio_id', $folio->id)->count())->toBe(1);
});

it('refuses to issue a receipt for a voided folio', function (): void {
    $folio = Folio::query()->create([
        'hotel_id' => $this->hotel->id,
        'total_amount' => 50_000_00,
        'status' => 'void',
    ]);

    $this->action->execute($folio);
})->throws(RuntimeException::class, 'voided');

it('increments sequence per hotel', function (): void {
    $folio1 = Folio::query()->create(['hotel_id' => $this->hotel->id, 'total_amount' => 10_000_00, 'status' => 'settled']);
    $folio2 = Folio::query()->create(['hotel_id' => $this->hotel->id, 'total_amount' => 20_000_00, 'status' => 'settled']);

    $r1 = $this->action->execute($folio1);
    $r2 = $this->action->execute($folio2);

    expect($r1->sequence)->toBe(1)
        ->and($r2->sequence)->toBe(2);
});
