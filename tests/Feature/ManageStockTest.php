<?php

use App\Models\Hotel;
use App\Models\Outlet;

beforeEach(function (): void {
    [$this->hotel] = actingAsTenantUser();
});

test('dashboard and sidebar link to Manage Stock instead of Outlets & menus', function (): void {
    $response = $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('manage-stock'), false)
        ->assertDontSee('Outlets &amp; menus', false);

    expect(substr_count($response->getContent(), 'Manage Stock'))->toBeGreaterThanOrEqual(2);
});

test('the Manage Stock page lists the hotel outlets as blocks', function (): void {
    $bar = Outlet::factory()->for($this->hotel)->create(['name' => 'Pool Bar']);
    $restaurant = Outlet::factory()->for($this->hotel)->create(['name' => 'Main Restaurant']);
    Outlet::factory()->for(Hotel::factory())->create(['name' => 'Someone Elses Bar']);

    $this->get(route('manage-stock'))
        ->assertOk()
        ->assertSee('Select the outlet')
        ->assertSee(route('manage-stock.outlet', $bar), false)
        ->assertSee(route('manage-stock.outlet', $restaurant), false)
        ->assertDontSee('Someone Elses Bar')
        ->assertSee(route('outlets.index'), false);
});

test('the Manage Stock page prompts to add an outlet when there are none', function (): void {
    $this->get(route('manage-stock'))
        ->assertOk()
        ->assertSee('Add your first outlet');
});

test('selecting an outlet shows its stock action blocks', function (): void {
    $outlet = Outlet::factory()->for($this->hotel)->create(['name' => 'Pool Bar']);

    $this->get(route('manage-stock.outlet', $outlet))
        ->assertOk()
        ->assertSee('Manage Stock — Pool Bar')
        ->assertSeeInOrder([
            'Add Supplier', 'View Supplier',
            'Add Stock Type', 'View Stock Type',
            'Add Categories', 'View Categories',
            'Add Products', 'View Products',
            'Receive Stock', 'Issue Products', 'Issued Stock',
        ])
        ->assertDontSee('Soon')
        ->assertSee(route('menu.index', $outlet), false)
        ->assertSee(e(route('suppliers.create', ['outlet' => $outlet->id])), false)
        ->assertSee(e(route('stock-types.index', ['outlet' => $outlet->id])), false)
        ->assertSee(route('manage-stock.issue', $outlet), false)
        ->assertSee(e(route('reports.stock', ['outlet' => $outlet->id, 'view' => 'issued'])), false)
        ->assertSee(e(route('reports.stock', ['outlet' => $outlet->id, 'view' => 'on-hand'])), false)
        ->assertSee(route('manage-stock'), false);
});

test('an outlet from another hotel cannot be opened', function (): void {
    $foreignOutlet = Outlet::factory()->for(Hotel::factory())->create();

    $this->get(route('manage-stock.outlet', $foreignOutlet))->assertNotFound();
});
