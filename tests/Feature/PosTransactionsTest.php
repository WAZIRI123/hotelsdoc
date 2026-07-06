<?php

declare(strict_types=1);

use App\Livewire\POS\Terminal;
use App\Livewire\POS\Transaction as PosTransactionPage;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\PosOrder;
use Livewire\Livewire;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();
    $this->outlet = Outlet::factory()->for($this->hotel)->create(['name' => 'Main Bar']);
    $cat = MenuCategory::factory()->for($this->hotel)->for($this->outlet)->create();
    $this->beer = MenuItem::factory()->for($this->hotel)->for($this->outlet)->for($cat, 'category')->create([
        'name' => 'Tusker',
        'price' => 5_000_00,
    ]);
});

it('lists billed transactions in the terminal and links to the detail page', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('openSettle')
        ->set('settleMethod', 'cash')
        ->call('settleDirect');

    $billed = PosOrder::query()->where('status', 'billed')->firstOrFail();

    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->assertSee($billed->reference)
        ->assertSee(route('pos.transactions.show', $billed));
});

it('renders the transaction detail page with items and totals', function (): void {
    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->call('addItem', $this->beer->id)
        ->call('addItem', $this->beer->id)
        ->call('openSettle')
        ->set('settleMethod', 'cash')
        ->call('settleDirect');

    $billed = PosOrder::query()->where('status', 'billed')->firstOrFail();

    Livewire::test(PosTransactionPage::class, ['posOrder' => $billed])
        ->assertSee($billed->reference)
        ->assertSee('Tusker')
        ->assertSee($billed->total_amount->format());
});

it('scopes transactions to the current outlet only', function (): void {
    $otherOutlet = Outlet::factory()->for($this->hotel)->create(['name' => 'Pool Bar']);
    PosOrder::factory()->for($this->hotel)->for($otherOutlet)->create([
        'status' => 'billed',
        'reference' => 'PO-OTHER01',
    ]);

    Livewire::test(Terminal::class, ['outlet' => $this->outlet])
        ->assertDontSee('PO-OTHER01');
});
