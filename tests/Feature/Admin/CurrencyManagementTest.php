<?php

declare(strict_types=1);

use App\Livewire\Admin\Currencies\Create;
use App\Livewire\Admin\Currencies\Index;
use App\Models\Currency;
use App\Models\Hotel;
use App\Models\User;
use Livewire\Livewire;

it('forbids hotel staff from managing currencies', function (): void {
    actingAsTenantUser(role: 'admin');

    $this->get(route('admin.currencies.create'))->assertForbidden();
    $this->get(route('admin.currencies.index'))->assertForbidden();
});

it('adds a currency to the chosen organization only', function (): void {
    $hotel = Hotel::factory()->create(['name' => 'Trial Hotel', 'currency' => 'TZS']);
    $otherHotel = Hotel::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    $this->get(route('admin.currencies.create'))->assertOk()->assertSee('Trial Hotel');

    Livewire::test(Create::class)
        ->set('hotelId', $hotel->id)
        ->set('code', 'usd')
        ->set('name', 'US Dollar')
        ->set('symbol', '$')
        ->set('exchangeRate', '2500')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('code', '');

    $currency = Currency::query()->sole();
    expect($currency->hotel_id)->toBe($hotel->id)
        ->and($currency->code)->toBe('USD')
        ->and($currency->name)->toBe('US Dollar')
        ->and((float) $currency->exchange_rate)->toBe(2500.0)
        ->and($currency->created_by)->toBe($superAdmin->id)
        ->and(Currency::query()->where('hotel_id', $otherHotel->id)->exists())->toBeFalse();
});

it('validates the currency form', function (): void {
    $hotel = Hotel::factory()->create(['currency' => 'TZS']);
    Currency::factory()->create(['hotel_id' => $hotel->id, 'code' => 'USD']);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Create::class)
        ->set('code', 'EUR')
        ->call('save')
        ->assertHasErrors(['hotelId', 'name', 'exchangeRate']);

    Livewire::test(Create::class)
        ->set('hotelId', $hotel->id)
        ->set('code', 'USD')
        ->set('name', 'US Dollar')
        ->set('exchangeRate', '2500')
        ->call('save')
        ->assertHasErrors(['code' => 'unique']);

    Livewire::test(Create::class)
        ->set('hotelId', $hotel->id)
        ->set('code', 'TZS')
        ->set('name', 'Shilling')
        ->set('exchangeRate', '1')
        ->call('save')
        ->assertHasErrors(['code' => 'not_in']);

    Livewire::test(Create::class)
        ->set('hotelId', $hotel->id)
        ->set('code', 'GBP')
        ->set('name', 'Pound')
        ->set('exchangeRate', '0')
        ->call('save')
        ->assertHasErrors(['exchangeRate']);

    expect(Currency::query()->count())->toBe(1);
});

it('lets the same currency be added to different organizations', function (): void {
    $first = Hotel::factory()->create(['currency' => 'TZS']);
    $second = Hotel::factory()->create(['currency' => 'KES']);
    Currency::factory()->create(['hotel_id' => $first->id, 'code' => 'USD']);
    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Create::class)
        ->set('hotelId', $second->id)
        ->set('code', 'USD')
        ->set('name', 'US Dollar')
        ->set('exchangeRate', '130')
        ->call('save')
        ->assertHasNoErrors();

    expect(Currency::query()->where('code', 'USD')->count())->toBe(2);
});

it('lists currencies filtered by organization and deletes them', function (): void {
    $first = Hotel::factory()->create(['name' => 'Alpha Lodge', 'currency' => 'TZS']);
    $second = Hotel::factory()->create(['name' => 'Beta Inn']);
    $usd = Currency::factory()->create(['hotel_id' => $first->id, 'code' => 'USD', 'name' => 'US Dollar', 'exchange_rate' => 2500]);
    Currency::factory()->create(['hotel_id' => $second->id, 'code' => 'EUR', 'name' => 'Euro']);
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get(route('admin.currencies.index'))
        ->assertOk()
        ->assertSee(['US Dollar', 'Euro', '1 USD = 2,500 TZS']);

    Livewire::test(Index::class)
        ->set('hotelId', $first->id)
        ->assertSee('US Dollar')
        ->assertDontSee('Euro')
        ->call('delete', $usd->id)
        ->assertDontSee('US Dollar')
        ->set('hotelId', '')
        ->assertSet('hotelId', null)
        ->assertSee('Euro');

    expect(Currency::query()->whereKey($usd->id)->exists())->toBeFalse();
});
