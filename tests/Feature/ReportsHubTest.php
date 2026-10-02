<?php

declare(strict_types=1);

use App\Domain\Reports\ReportCatalog;
use App\Livewire\Reports\Index;
use Livewire\Livewire;

it('shows every report tile to an accountant', function (): void {
    actingAsTenantUser(role: 'accountant');

    $keys = array_keys(Livewire::test(Index::class)->instance()->reports());

    expect($keys)->toBe(array_keys(ReportCatalog::REPORTS));

    $this->get(route('reports.index'))
        ->assertOk()
        ->assertSee('Accountant Reports')
        ->assertSee('Profit &amp; Loss Report', false)
        ->assertSee(route('reports.cash-up', ['view' => 'reservations']), false);
});

it('limits front desk to reservation and folio reports', function (): void {
    actingAsTenantUser(role: 'front_desk');

    $keys = array_keys(Livewire::test(Index::class)->instance()->reports());

    expect($keys)->toBe(['reservations', 'extras', 'credit', 'agents']);
});

it('shows no reports to plain staff', function (): void {
    actingAsTenantUser(role: 'staff');

    $this->get(route('reports.index'))
        ->assertOk()
        ->assertSee('You do not have access to any reports.');
});

it('links the sidebar Get Reports item to the hub', function (): void {
    actingAsTenantUser();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Get Reports')
        ->assertSee(route('reports.index'), false);
});
