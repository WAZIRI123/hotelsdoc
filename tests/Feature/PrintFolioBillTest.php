<?php

declare(strict_types=1);

use App\Models\Folio;
use App\Models\Guest;

beforeEach(function (): void {
    [$this->hotel, $this->user] = actingAsTenantUser();

    $this->hotel->update([
        'name' => 'Serengeti Boutique Hotel',
        'address' => 'Plot 12, Msasani Peninsula, Dar es Salaam',
        'phone' => '+255 22 000 0000',
        'email' => 'billing@serengetihotel.co.tz',
        'tin' => '123-456-789',
        'vrn' => '40-000000-A',
    ]);

    $this->guest = Guest::factory()->for($this->hotel)->create([
        'first_name' => 'Amina',
        'last_name' => 'Hassan',
    ]);

    $this->folio = Folio::query()->create([
        'hotel_id' => $this->hotel->id,
        'guest_id' => $this->guest->id,
        'status' => 'open',
        'subtotal' => 120_000_00,
        'tax_total' => 21_600_00,
        'total_amount' => 141_600_00,
        'paid_total' => 50_000_00,
    ]);

    $this->folio->charges()->create([
        'hotel_id' => $this->hotel->id,
        'source_type' => 'room_night',
        'description' => 'Deluxe Room — 2 nights',
        'quantity' => 2,
        'unit_price' => 60_000_00,
        'total' => 120_000_00,
    ]);

    $this->folio->payments()->create([
        'hotel_id' => $this->hotel->id,
        'method_code' => 'cash',
        'amount' => 50_000_00,
        'currency' => 'TZS',
        'status' => 'confirmed',
    ]);
});

it('renders a professional printable bill for a folio', function (): void {
    $response = $this->get(route('folios.print', $this->folio));

    $response->assertOk()
        ->assertSee('Serengeti Boutique Hotel')
        ->assertSee('Amina Hassan')
        ->assertSee($this->folio->reference)
        ->assertSee('Deluxe Room — 2 nights', false)
        ->assertSee('TZS 141,600')
        ->assertSee('TZS 50,000')
        ->assertSee('Balance due')
        ->assertSee('Cashier signature');
});

it('flags the bill as paid in full when balance is zero', function (): void {
    $this->folio->update(['paid_total' => 141_600_00]);

    $this->get(route('folios.print', $this->folio))
        ->assertOk()
        ->assertSee('Paid in full')
        ->assertDontSee('Balance due');
});

it('requires authentication to view a bill', function (): void {
    auth()->logout();

    $this->get(route('folios.print', $this->folio))
        ->assertRedirect(route('login'));
});
