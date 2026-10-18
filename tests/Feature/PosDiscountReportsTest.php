<?php

declare(strict_types=1);

use App\Domain\Folios\Actions\ApplyFolioDiscount;
use App\Domain\Folios\Actions\PostFolioCharge;
use App\Livewire\POS\Terminal;
use App\Livewire\Reports\CashUp;
use App\Livewire\Reports\DrinksSales;
use App\Livewire\Reports\ProfitLoss;
use App\Models\Folio;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Support\Money;
use App\Support\Vat;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00'));
    $this->outlet = Outlet::factory()->for($this->hotel)->create(['name' => 'Main Bar', 'type' => 'bar']);
    $category = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();
    $this->beer = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($category, 'category')->create(['name' => 'Tusker', 'price' => 5_000_00]);
    $this->snack = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($category, 'category')->create(['name' => 'Chips Mayai', 'price' => 8_000_00]);

    // 2 × Tusker less a 1,000 discount, plus Chips Mayai cut from 8,000 to 6,000: 18,000 rung up, 15,000 paid.
    $terminal = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('addItem', $this->beer->id)
        ->call('addItem', $this->snack->id);

    [$beerLine, $snackLine] = $terminal->instance()->order()->items->sortBy('id')->values()->all();

    $terminal->call('openAdjustLine', $beerLine->id)
        ->set('adjustDiscount', '1000')
        ->set('adjustReason', 'Regular customer')
        ->call('saveLineAdjustment')
        ->call('openAdjustLine', $snackLine->id)
        ->set('adjustUnitPrice', '6000')
        ->set('adjustReason', 'Served cold')
        ->call('saveLineAdjustment')
        ->call('openSettle')
        ->set('settleMethod', 'cash')
        ->call('settleDirect')
        ->assertHasNoErrors();
});

it('shows till discounts in the POS sales report', function (): void {
    $component = Livewire::test(DrinksSales::class);

    $row = $component->instance()->orderRows()[0];
    expect($row['total']->toMajor())->toBe(18_000.0)
        ->and($row['discount']->toMajor())->toBe(3_000.0)
        ->and($row['grand_total']->toMajor())->toBe(15_000.0)
        ->and($component->instance()->totals()['discount']->toMajor())->toBe(3_000.0);
});

it('reports POS sales before till discounts and takes them off under discounts', function (): void {
    $component = Livewire::test(ProfitLoss::class)
        ->assertSee('after discounts of 3,000');

    $income = $component->instance()->income();
    expect($income['Drinks Sales']->toMajor())->toBe(18_000.0)
        ->and($income['Less: Discounts']->toMajor())->toBe(-3_000.0)
        ->and($component->instance()->totalIncome()->toMajor())->toBe(15_000.0)
        ->and($component->instance()->salesColumns()['food_drinks']->toMajor())->toBe(15_000.0)
        ->and($component->instance()->salesColumns()['accommodation']->toMajor())->toBe(0.0);
});

it('keeps bill discounts with accommodation and till discounts with food and drinks', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'status' => 'open', 'total_amount' => 0]);
    app(PostFolioCharge::class)->amounts($folio, 'room', null, 'Room night', Money::fromMajor(50_000), Money::zero());
    app(ApplyFolioDiscount::class)->execute($folio->fresh(), Money::fromMajor(5_000), 'Loyalty');

    $component = Livewire::test(ProfitLoss::class);

    expect($component->instance()->income()['Less: Discounts']->toMajor())->toBe(-8_000.0)
        ->and($component->instance()->salesColumns()['food_drinks']->toMajor())->toBe(15_000.0)
        ->and($component->instance()->salesColumns()['accommodation']->toMajor())->toBe(45_000.0);
});

it('lists bill and till discounts in the cash-up', function (): void {
    $folio = Folio::query()->create(['hotel_id' => $this->hotel->id, 'status' => 'open', 'total_amount' => 0]);
    app(PostFolioCharge::class)->amounts($folio, 'room', null, 'Room night', Money::fromMajor(50_000), Money::zero());
    app(ApplyFolioDiscount::class)->execute($folio->fresh(), Money::fromMajor(5_000), 'Loyalty', $this->user);

    $component = Livewire::test(CashUp::class, ['view' => 'general'])
        ->assertSee('Discounts given')
        ->assertSee('2 × Tusker — Regular customer')
        ->assertSee('1 × Chips Mayai — Served cold')
        ->assertSee('Loyalty');

    $rows = collect($component->instance()->discountsGiven());
    expect($rows)->toHaveCount(3)
        ->and($rows->firstWhere('reason', '2 × Tusker — Regular customer')['amount']->toMajor())->toBe(1_000.0)
        ->and($rows->firstWhere('reason', '1 × Chips Mayai — Served cold')['amount']->toMajor())->toBe(2_000.0)
        ->and($rows->firstWhere('reason', '1 × Chips Mayai — Served cold')['by'])->toBe($this->user->name)
        ->and($rows->firstWhere('reason', 'Loyalty')['amount']->toMajor())->toBe(5_000.0)
        ->and($rows->firstWhere('reason', 'Loyalty')['by'])->toBe($this->user->name)
        ->and($component->instance()->totalDiscountsGiven()->toMajor())->toBe(8_000.0);
});

it('does not count a price raised at the till as a discount', function (): void {
    $terminal = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id);
    $line = $terminal->instance()->order()->items->first();

    $terminal->call('openAdjustLine', $line->id)
        ->set('adjustUnitPrice', '6000')
        ->set('adjustReason', 'Large size')
        ->call('saveLineAdjustment')
        ->call('openSettle')
        ->set('settleMethod', 'cash')
        ->call('settleDirect');

    expect($line->fresh()->amountOff()->isZero())->toBeTrue()
        ->and(collect(Livewire::test(CashUp::class, ['view' => 'general'])->instance()->discountsGiven()))->toHaveCount(2)
        ->and(Livewire::test(ProfitLoss::class)->instance()->posDiscounts()->toMajor())->toBe(3_000.0);
});

it('keeps the VAT a till discount was given under when the VAT setting changes later', function (): void {
    $this->hotel->update(['vat_mode' => Vat::INCLUSIVE, 'vat_rate' => 18]);

    $terminal = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id);
    $line = $terminal->instance()->order()->items->first();

    $terminal->call('openAdjustLine', $line->id)
        ->set('adjustDiscount', '1180')
        ->set('adjustReason', 'Promo')
        ->call('saveLineAdjustment')
        ->call('openSettle')
        ->set('settleMethod', 'cash')
        ->call('settleDirect');

    expect($line->fresh()->amount_off_net->toMajor())->toBe(1_000.0)
        ->and($line->fresh()->amount_off_gross->toMajor())->toBe(1_180.0);

    $this->hotel->update(['vat_mode' => Vat::NONE, 'vat_rate' => 0]);

    // 3,000 from the order rung up before VAT was switched on, 1,000 before VAT from this one.
    expect(Livewire::test(ProfitLoss::class)->instance()->posDiscounts()->toMajor())->toBe(4_000.0)
        ->and(collect(Livewire::test(CashUp::class, ['view' => 'general'])->instance()->discountsGiven())->firstWhere('reason', '1 × Tusker — Promo')['amount']->toMajor())->toBe(1_180.0);
});
