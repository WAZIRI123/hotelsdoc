<?php

declare(strict_types=1);

use App\Domain\Stock\Actions\IssueStock;
use App\Domain\Stock\Actions\RecordStockEntry;
use App\Livewire\Menu\Index as MenuIndex;
use App\Livewire\Reports\ProfitLoss;
use App\Livewire\Reports\StockProducts;
use App\Livewire\Stock\Issue;
use App\Models\Hotel;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\StockEntry;
use App\Models\StockIssue;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Creates a product on a fresh category of the given outlet.
 *
 * @param  array<string, mixed>  $attributes
 */
function stockProduct(Outlet $outlet, string $name, array $attributes = []): MenuItem
{
    $category = MenuCategory::factory()->for($outlet->hotel)->for($outlet)->create();

    return MenuItem::factory()->for($outlet->hotel)->for($outlet)->for($category, 'category')->create(['name' => $name, ...$attributes]);
}

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->store = Outlet::factory()->for($this->hotel)->create(['name' => 'Main Store', 'type' => 'shop']);
    $this->bar = Outlet::factory()->for($this->hotel)->create(['name' => 'Pool Bar', 'type' => 'bar']);
    $this->storeBeer = stockProduct($this->store, 'Tusker Crate Stock', ['track_stock' => true, 'stock_quantity' => 20]);
    $this->barBeer = stockProduct($this->bar, 'Tusker', ['track_stock' => false, 'stock_quantity' => 0]);
});

it('issues stock for internal use and takes it off stock on hand', function (): void {
    Livewire::test(Issue::class, ['outlet' => $this->store])
        ->set('itemId', (string) $this->storeBeer->id)
        ->set('quantity', '3')
        ->set('reason', StockIssue::REASON_INTERNAL_USE)
        ->set('issuedTo', 'Kitchen')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Kitchen');

    expect(StockIssue::query()->sole())
        ->quantity->toBe(3)
        ->issued_to->toBe('Kitchen')
        ->recorded_by->toBe($this->user->id)
        ->and($this->storeBeer->fresh()->stock_quantity)->toBe(17);
});

it('transfers stock to another outlet product and switches its tracking on', function (): void {
    Livewire::test(Issue::class, ['outlet' => $this->store])
        ->set('itemId', (string) $this->storeBeer->id)
        ->set('quantity', '12')
        ->set('reason', StockIssue::REASON_TRANSFER)
        ->set('toItemId', (string) $this->barBeer->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($this->storeBeer->fresh()->stock_quantity)->toBe(8)
        ->and($this->barBeer->fresh()->track_stock)->toBeTrue()
        ->and($this->barBeer->fresh()->stock_quantity)->toBe(12)
        ->and(StockIssue::query()->sole()->to_menu_item_id)->toBe($this->barBeer->id);
});

it('reverses a transfer on both products', function (): void {
    $issue = app(IssueStock::class)->execute(
        item: $this->storeBeer,
        quantity: 5,
        reason: StockIssue::REASON_TRANSFER,
        issueDate: CarbonImmutable::today(),
        transferTo: $this->barBeer,
    );

    Livewire::test(Issue::class, ['outlet' => $this->store])->call('reverse', $issue->id);

    expect(StockIssue::query()->count())->toBe(0)
        ->and($this->storeBeer->fresh()->stock_quantity)->toBe(20)
        ->and($this->barBeer->fresh()->stock_quantity)->toBe(0);
});

it('refuses to issue more than is in stock or a transfer without a receiving product', function (): void {
    Livewire::test(Issue::class, ['outlet' => $this->store])
        ->set('itemId', (string) $this->storeBeer->id)
        ->set('quantity', '21')
        ->call('save')
        ->assertHasErrors(['quantity' => 'max']);

    Livewire::test(Issue::class, ['outlet' => $this->store])
        ->set('itemId', (string) $this->storeBeer->id)
        ->set('quantity', '1')
        ->set('reason', StockIssue::REASON_TRANSFER)
        ->call('save')
        ->assertHasErrors(['toItemId' => 'required']);

    expect(StockIssue::query()->count())->toBe(0)
        ->and($this->storeBeer->fresh()->stock_quantity)->toBe(20);
});

it('only issues from the outlet stock-tracked products', function (): void {
    stockProduct($this->store, 'Untracked Napkins');

    Livewire::test(Issue::class, ['outlet' => $this->store])
        ->assertSee('Tusker Crate Stock')
        ->assertDontSee('Untracked Napkins')
        ->set('itemId', (string) $this->barBeer->id)
        ->set('quantity', '1')
        ->call('save')
        ->assertHasErrors(['itemId']);
});

it('forbids issuing stock without manage-stock or from another hotel outlet', function (): void {
    $this->get(route('manage-stock.issue', Outlet::factory()->for(Hotel::factory())->create()))->assertNotFound();

    actingAsTenantUser($this->hotel, User::factory()->create(), 'cashier');

    $this->get(route('manage-stock.issue', $this->store))->assertForbidden();
});

it('shows issued stock and received, issued and sold columns in the stock report', function (): void {
    CarbonImmutable::setTestNow('2026-09-20 09:00:00');

    $issueStock = app(IssueStock::class);
    $issueStock->execute($this->storeBeer, 12, StockIssue::REASON_TRANSFER, CarbonImmutable::parse('2026-09-10'), transferTo: $this->barBeer);
    $issueStock->execute($this->storeBeer, 2, StockIssue::REASON_WASTAGE, CarbonImmutable::parse('2026-09-12'), issuedTo: 'Broken bottles');
    StockEntry::factory()->for($this->hotel)->for($this->storeBeer)->create(['entry_date' => '2026-09-01', 'quantity' => 6]);

    $component = Livewire::test(StockProducts::class, ['from' => '2026-09-01', 'to' => '2026-09-30', 'outlet' => (string) $this->store->id])
        ->call('showView', 'issued')
        ->assertSee('Transfer to another outlet')
        ->assertSee('Tusker · Pool Bar')
        ->assertSee('Broken bottles')
        ->assertSee('14.00');

    expect($component->instance()->issues()->pluck('quantity')->all())->toBe([12, 2]);

    $rows = collect(Livewire::test(StockProducts::class, ['from' => '2026-09-01', 'to' => '2026-09-30'])->instance()->rows())->keyBy('name');

    expect($rows['Tusker Crate Stock'])
        ->received->toBe(6)
        ->issued->toBe(14)
        ->in_stock->toBe(6)
        ->and($rows['Tusker'])
        ->received->toBe(12)
        ->issued->toBe(0)
        ->in_stock->toBe(12);

    CarbonImmutable::setTestNow();
});

it('records supplier and unit cost on stock received', function (): void {
    $supplier = Supplier::factory()->for($this->hotel)->create(['name' => 'Serengeti Breweries']);

    $component = Livewire::test(StockProducts::class)
        ->call('openCreate')
        ->set('entryItem', (string) $this->storeBeer->id)
        ->set('entrySupplier', (string) $supplier->id)
        ->set('entryQuantity', '24')
        ->set('entryUnitCost', '1500')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Serengeti Breweries')
        ->assertSee('36,000');

    expect(StockEntry::query()->sole())
        ->supplier_id->toBe($supplier->id)
        ->unit_cost->toBe(150_000)
        ->and($component->instance()->entriesCost()->toMajor())->toBe(36_000.0)
        ->and($this->storeBeer->fresh()->stock_quantity)->toBe(44);
});

it('keeps tracked stock on hand out of the menu edit form', function (): void {
    Livewire::test(MenuIndex::class, ['outlet' => $this->store])
        ->call('openEditItem', $this->storeBeer->id)
        ->assertSet('itemWasTracked', true)
        ->set('itemStockQuantity', 999)
        ->call('saveItem')
        ->assertHasNoErrors();

    expect($this->storeBeer->fresh()->stock_quantity)->toBe(20);

    Livewire::test(MenuIndex::class, ['outlet' => $this->bar])
        ->call('openEditItem', $this->barBeer->id)
        ->set('itemTrackStock', true)
        ->set('itemStockQuantity', 7)
        ->call('saveItem')
        ->assertHasNoErrors();

    expect($this->barBeer->fresh())->track_stock->toBeTrue()->stock_quantity->toBe(7);
});

it('records opening stock from the menu as a stock entry kept out of stock purchases', function (): void {
    CarbonImmutable::setTestNow('2026-10-02 09:00');

    Livewire::test(MenuIndex::class, ['outlet' => $this->bar])
        ->call('openEditItem', $this->barBeer->id)
        ->set('itemTrackStock', true)
        ->set('itemStockQuantity', 7)
        ->call('saveItem')
        ->assertHasNoErrors();

    $entry = StockEntry::query()->where('menu_item_id', $this->barBeer->id)->sole();

    expect($entry)
        ->quantity->toBe(7)
        ->description->toBe('Opening stock')
        ->unit_cost->toBeNull()
        ->recorded_by->toBe($this->user->id)
        ->and($entry->entry_date->toDateString())->toBe('2026-10-02')
        ->and($this->barBeer->fresh()->stock_quantity)->toBe(7)
        ->and(Livewire::test(ProfitLoss::class, ['from' => '2026-10-01', 'to' => '2026-10-31'])->instance()->stockPurchases()->isZero())->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('refuses to reverse a transfer whose stock has already been used', function (): void {
    $issue = app(IssueStock::class)->execute(
        item: $this->storeBeer,
        quantity: 5,
        reason: StockIssue::REASON_TRANSFER,
        issueDate: CarbonImmutable::today(),
        transferTo: $this->barBeer,
    );
    $this->barBeer->fresh()->decrement('stock_quantity', 4);

    Livewire::test(Issue::class, ['outlet' => $this->store])->call('reverse', $issue->id);

    expect(StockIssue::query()->count())->toBe(1)
        ->and($this->storeBeer->fresh()->stock_quantity)->toBe(15)
        ->and($this->barBeer->fresh()->stock_quantity)->toBe(1);
});

it('refuses to delete a stock entry whose stock has already been used', function (): void {
    $entry = app(RecordStockEntry::class)->execute($this->storeBeer, 10, CarbonImmutable::today());
    $this->storeBeer->fresh()->decrement('stock_quantity', 25);

    Livewire::test(StockProducts::class)->call('delete', $entry->id);

    expect(StockEntry::query()->whereKey($entry->id)->exists())->toBeTrue()
        ->and($this->storeBeer->fresh()->stock_quantity)->toBe(5);
});
