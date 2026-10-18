<?php

declare(strict_types=1);

use App\Livewire\Menu\Index as MenuIndex;
use App\Livewire\POS\Terminal;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrderItem;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser(role: 'manager');
    $this->outlet = Outlet::factory()->for($this->hotel)->create();
    $this->category = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();
    $this->beer = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($this->category, 'category')->create([
        'name' => 'Tusker',
        'price' => 5_000_00,
    ]);
});

function terminalWithBeers(Outlet $outlet, MenuItem $beer, int $count): array
{
    $component = Livewire::test(Terminal::class, ['outlet' => $outlet]);

    foreach (range(1, $count) as $ignored) {
        $component->call('addItem', $beer->id);
    }

    return [$component, $component->instance()->order()->items->first()];
}

it('lets a manager override a line price, keeping the rung-up price and reason', function (): void {
    [$component, $line] = terminalWithBeers($this->outlet, $this->beer, 2);

    $component->call('openAdjustLine', $line->id)
        ->assertSet('adjustUnitPrice', '5000')
        ->set('adjustUnitPrice', '4000')
        ->set('adjustReason', 'Happy hour')
        ->call('saveLineAdjustment')
        ->assertHasNoErrors();

    $line->refresh();
    expect($line->unit_price->toMajor())->toBe(4_000.0)
        ->and($line->original_unit_price->toMajor())->toBe(5_000.0)
        ->and($line->line_total->toMajor())->toBe(8_000.0)
        ->and($line->adjustment_reason)->toBe('Happy hour')
        ->and($line->adjusted_by)->toBe($this->user->id)
        ->and($component->instance()->order()->total_amount->toMajor())->toBe(8_000.0);
});

it('takes a discount off a line', function (): void {
    [$component, $line] = terminalWithBeers($this->outlet, $this->beer, 2);

    $component->call('openAdjustLine', $line->id)
        ->set('adjustDiscount', '1500.50')
        ->set('adjustReason', 'Regular customer')
        ->call('saveLineAdjustment')
        ->assertHasNoErrors();

    $line->refresh();
    expect($line->discount_amount->amount)->toBe(1_500_50)
        ->and($line->original_unit_price)->toBeNull()
        ->and($line->line_total->amount)->toBe(8_499_50)
        ->and($component->instance()->order()->total_amount->amount)->toBe(8_499_50);
});

it('requires a reason and keeps the discount within the line', function (): void {
    [$component, $line] = terminalWithBeers($this->outlet, $this->beer, 1);

    $component->call('openAdjustLine', $line->id)
        ->set('adjustDiscount', '1000')
        ->call('saveLineAdjustment')
        ->assertHasErrors('adjustReason')
        ->set('adjustReason', 'Too much')
        ->set('adjustDiscount', '6000')
        ->call('saveLineAdjustment')
        ->assertHasErrors('adjustDiscount');

    expect($line->fresh()->isAdjusted())->toBeFalse()
        ->and($line->fresh()->line_total->toMajor())->toBe(5_000.0);
});

it('clears the adjustment when the line goes back to its rung-up price', function (): void {
    [$component, $line] = terminalWithBeers($this->outlet, $this->beer, 1);

    $component->call('openAdjustLine', $line->id)
        ->set('adjustUnitPrice', '4000')
        ->set('adjustReason', 'Mistake')
        ->call('saveLineAdjustment')
        ->call('openAdjustLine', $line->id)
        ->set('adjustUnitPrice', '5000')
        ->call('saveLineAdjustment')
        ->assertHasNoErrors();

    $line->refresh();
    expect($line->isAdjusted())->toBeFalse()
        ->and($line->unit_price->toMajor())->toBe(5_000.0)
        ->and($line->adjustment_reason)->toBeNull()
        ->and($line->adjusted_by)->toBeNull();
});

it('keeps adjusted lines apart from new units of the same item', function (): void {
    [$component, $line] = terminalWithBeers($this->outlet, $this->beer, 1);

    $component->call('openAdjustLine', $line->id)
        ->set('adjustUnitPrice', '4000')
        ->set('adjustReason', 'Happy hour')
        ->call('saveLineAdjustment')
        ->call('addItem', $this->beer->id);

    $lines = $component->instance()->order()->items->sortBy('id')->values();
    expect($lines)->toHaveCount(2)
        ->and($lines[0]->unit_price->toMajor())->toBe(4_000.0)
        ->and($lines[1]->unit_price->toMajor())->toBe(5_000.0)
        ->and($component->instance()->order()->total_amount->toMajor())->toBe(9_000.0);
});

it('shrinks a discount that no longer fits when the quantity drops', function (): void {
    [$component, $line] = terminalWithBeers($this->outlet, $this->beer, 2);

    $component->call('openAdjustLine', $line->id)
        ->set('adjustDiscount', '7000')
        ->set('adjustReason', 'Promo')
        ->call('saveLineAdjustment')
        ->call('changeQty', $line->id, -1);

    $line->refresh();
    expect($line->quantity)->toBe(1)
        ->and($line->discount_amount->toMajor())->toBe(5_000.0)
        ->and($line->line_total->toMajor())->toBe(0.0);
});

it('does not let staff without the permission change prices', function (): void {
    actingAsTenantUser($this->hotel, User::factory()->create(), 'waiter');
    [$component, $line] = terminalWithBeers($this->outlet, $this->beer, 1);

    expect($component->instance()->canAdjustPrices())->toBeFalse();

    $component->call('openAdjustLine', $line->id)->assertForbidden();

    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->set('adjustingLineId', $line->id)
        ->set('adjustUnitPrice', '1')
        ->set('adjustReason', 'Sneaky')
        ->call('saveLineAdjustment')
        ->assertForbidden();

    expect($line->fresh()->unit_price->toMajor())->toBe(5_000.0);
});

it('asks for the price of an open-price item and keeps each entry on its own line', function (): void {
    $this->beer->update(['is_open_price' => true, 'price' => 0]);

    $component = Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->assertSet('openPriceItemId', $this->beer->id);

    expect($component->instance()->order())->toBeNull();

    $component->set('openPrice', '3500')
        ->call('confirmOpenPrice')
        ->assertHasNoErrors()
        ->call('addItem', $this->beer->id)
        ->set('openPrice', '3500')
        ->call('confirmOpenPrice');

    $lines = PosOrderItem::query()->orderBy('id')->get();
    expect($lines)->toHaveCount(2)
        ->and($lines[0]->unit_price->toMajor())->toBe(3_500.0)
        ->and($lines[1]->unit_price->toMajor())->toBe(3_500.0)
        ->and($component->instance()->order()->total_amount->toMajor())->toBe(7_000.0);
});

it('requires a price for an open-price item', function (): void {
    $this->beer->update(['is_open_price' => true, 'price' => 0]);

    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->assertSet('openPrice', '')
        ->call('confirmOpenPrice')
        ->assertHasErrors('openPrice');

    expect(PosOrderItem::query()->count())->toBe(0);
});

it('saves the open-price flag from the menu', function (): void {
    Livewire::test(MenuIndex::class, ['outlet' => $this->outlet])
        ->call('openEditItem', $this->beer->id)
        ->set('itemIsOpenPrice', true)
        ->call('saveItem')
        ->assertHasNoErrors();

    expect($this->beer->fresh()->is_open_price)->toBeTrue();
});
