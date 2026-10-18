<?php

declare(strict_types=1);

use App\Domain\Folios\Actions\ApplyFolioDiscount;
use App\Domain\Folios\Actions\PostFolioCharge;
use App\Livewire\Folios\Show;
use App\Livewire\Reports\ProfitLoss;
use App\Models\Folio;
use App\Models\Hotel;
use App\Support\Money;
use App\Support\Vat;
use Livewire\Livewire;

it('takes the entered amount off what the guest pays, VAT included', function (): void {
    [$hotel] = actingAsTenantUser(Hotel::factory()->create(['vat_mode' => Vat::EXCLUSIVE, 'vat_rate' => 18]));
    $folio = Folio::query()->create(['hotel_id' => $hotel->id, 'status' => 'open']);
    app(PostFolioCharge::class)->price($folio, 'room', null, 'Room', Money::fromMajor(100_000));

    $discount = app(ApplyFolioDiscount::class)->execute($folio->fresh(), Money::fromMajor(11_800), 'Loyal guest');

    expect($discount->source_type)->toBe('discount')
        ->and($discount->total->amount)->toBe(-11_800_00)
        ->and($discount->tax_amount->amount)->toBe(-1_800_00)
        ->and($folio->fresh()->total_amount->amount)->toBe(106_200_00);
});

it('refuses a discount larger than the folio total', function (): void {
    [$hotel] = actingAsTenantUser();
    $folio = Folio::query()->create(['hotel_id' => $hotel->id, 'status' => 'open', 'subtotal' => 50_000_00, 'total_amount' => 50_000_00]);

    app(ApplyFolioDiscount::class)->execute($folio, Money::fromMajor(50_001), 'Too much');
})->throws(RuntimeException::class, 'cannot be more than');

it('applies a discount from the folio page and reports it as a discount', function (): void {
    [$hotel] = actingAsTenantUser();
    $folio = Folio::query()->create(['hotel_id' => $hotel->id, 'status' => 'open']);
    app(PostFolioCharge::class)->price($folio, 'room', null, 'Room', Money::fromMajor(100_000));

    Livewire::test(Show::class, ['folio' => $folio->fresh()])
        ->call('openDiscount')
        ->set('discount_amount', 10_000)
        ->set('discount_reason', 'Late breakfast')
        ->call('applyDiscount')
        ->assertHasNoErrors()
        ->assertSee('Discount — Late breakfast');

    expect($folio->fresh()->total_amount->amount)->toBe(90_000_00)
        ->and($folio->charges()->where('source_type', 'discount')->value('posted_by'))->toBe(auth()->id());

    $income = Livewire::test(ProfitLoss::class)->instance()->income();
    expect($income[__('Less: Discounts')]->amount)->toBe(-10_000_00);
});

it('requires a reason for the discount', function (): void {
    [$hotel] = actingAsTenantUser();
    $folio = Folio::query()->create(['hotel_id' => $hotel->id, 'status' => 'open', 'subtotal' => 50_000_00, 'total_amount' => 50_000_00]);

    Livewire::test(Show::class, ['folio' => $folio])
        ->set('discount_amount', 1_000)
        ->call('applyDiscount')
        ->assertHasErrors(['discount_reason' => 'required']);
});
